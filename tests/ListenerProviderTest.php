<?php

namespace Jasny\ListenerProvider\Tests;

use Jasny\EventDispatcher\ListenerProvider;
use Jasny\EventDispatcher\Tests\Support\BeforeSaveEvent;
use Jasny\EventDispatcher\Tests\Support\ToJsonEvent;
use Jasny\EventDispatcher\Tests\Support\SumEvent;
use Jasny\ReflectionFactory\ReflectionFactory;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;

/**
 * @covers \Jasny\EventDispatcher\ListenerProvider
 */
class ListenerProviderTest extends TestCase
{
    /**
     * @var ListenerProvider
     */
    protected $provider;

    public function setUp()
    {
        $base = new ListenerProvider();

        $this->provider = $base
            ->withListener(function(BeforeSaveEvent $event) {
                $emitter = $event->getEmitter();
                $payload = $event->getPayload();

                $payload['bio'] = $payload['bio'] ?? "$emitter->name <{$emitter->email}> just arrived";
                $event->setPayload($payload);
            })
            ->withListenerInNs('censor', function(BeforeSaveEvent $event) {
                $emitter = $event->getEmitter();
                $payload = $event->getPayload();

                $payload['bio'] = strtr($payload['bio'], [$emitter->email => '***@***.***']);
                $event->setPayload($payload);
            })
            ->withListenerInNs('censor.json', function(ToJsonEvent $event) {
                $payload = $event->getPayload();
                unset($payload['password']);

                $event->setPayload($payload);
            })
            ->withListener(function(SumEvent $event) {
                $event->add(10);
            })
            ->withListener(function(SumEvent $event) {
                $event->add(20);
            });
    }

    public function testWithListener()
    {
        $base = new ListenerProvider();
        $provider = $base->withListener(function(BeforeSaveEvent $event) {});

        $this->assertInstanceOf(ListenerProvider::class, $provider);
        $this->assertNotSame($base, $provider);

        return $provider;
    }

    public function testWithListenerInNs()
    {
        $base = new ListenerProvider();
        $provider = $base->withListenerInNs('censor', function(BeforeSaveEvent $event) {});

        $this->assertInstanceOf(ListenerProvider::class, $provider);
        $this->assertNotSame($base, $provider);

        return $provider;
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid event ns '*.foo': illegal character '*'
     */
    public function testWithListenerInvalidNs()
    {
        (new ListenerProvider)->withListenerInNs('*.foo', function() {});
    }


    /**
     * @depends testWithListener
     */
    public function testGetListenersForBeforeSave()
    {
        $emitter = (object)[
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ];

        $event = $this->createMock(BeforeSaveEvent::class);
        $event->expects($this->any())->method('getEmitter')->willReturn($emitter);
        $event->expects($this->exactly(2))->method('getPayload')
            ->willReturnOnConsecutiveCalls([], ['bio' => "John Doe <john.doe@example.com> just arrived"]);
        $event->expects($this->any())->method('setPayload')
            ->withConsecutive(
                [['bio' => "John Doe <john.doe@example.com> just arrived"]],
                [['bio' => "John Doe <***@***.***> just arrived"]]
            );

        $listeners = $this->provider->getListenersForEvent($event);
        $this->assertCount(2, $listeners);

        ($listeners[0])($event);
        ($listeners[1])($event);
    }

    public function testGetListenersForJson()
    {
        $event = $this->createMock(ToJsonEvent::class);
        $event->expects($this->once())->method('getPayload')
            ->willReturn(['username' => 'john', 'password' => '12345']);
        $event->expects($this->once())->method('setPayload')
            ->willReturn(['username' => 'john']);

        $listeners = $this->provider->getListenersForEvent($event);
        $this->assertCount(1, $listeners);

        ($listeners[0])($event);
    }

    public function testGetListenersForSum()
    {
        $event = new SumEvent();

        $listeners = $this->provider->getListenersForEvent($event);
        $this->assertCount(2, $listeners);

        ($listeners[0])($event);
        ($listeners[1])($event);

        $this->assertEquals(30, $event->getTotal());
    }


    public function offProvider()
    {
        return [
            ['censor', 1, 0],
            ['censor.json', 2, 0],
            ['*.json', 2, 0],
        ];
    }

    /**
     * @dataProvider offProvider
     */
    public function testWithoutNs(string $ns, int $nrBeforeSave, int $nrJson)
    {
        $newProvider = $this->provider->withoutNs($ns);

        $this->assertInstanceOf(ListenerProvider::class, $newProvider);
        $this->assertNotSame($this->provider, $newProvider);

        $listeners = $newProvider->getListenersForEvent(new BeforeSaveEvent((object)[]));
        $this->assertCount($nrBeforeSave, $listeners);
        $this->assertCount($nrJson, $newProvider->getListenersForEvent(new ToJsonEvent()));
        $this->assertCount(2, $newProvider->getListenersForEvent(new SumEvent()));
    }

    public function testWithoutNsIdempotent()
    {
        $newProvider = $this->provider->withoutNs('censor');

        $this->assertInstanceOf(ListenerProvider::class, $newProvider);
        $this->assertNotSame($this->provider, $newProvider);

        $this->assertSame($newProvider, $newProvider->withoutNs('censor'));
        $this->assertSame($newProvider, $newProvider->withoutNs('censor.json')); // Already removed
        $this->assertSame($newProvider, $newProvider->withoutNs('does-not-exist'));
    }


    /**
     * @expectedException LogicException
     * @expectedExceptionMessage Invalid event listener: bad callable
     */
    public function testErrorReflectionException()
    {
        $function = function() {};

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $reflectionFactory->expects($this->once())->method('reflectFunction')
            ->with($this->identicalTo($function))
            ->willThrowException(new ReflectionException("bad callable"));

        $provider = new ListenerProvider($reflectionFactory);
        $provider->withListener($function);
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage Invalid event listener: No parameters defined
     */
    public function testErrorNoParameters()
    {
        $function = function() {};

        $reflFn = $this->createMock(ReflectionFunction::class);
        $reflFn->expects($this->once())->method('getNumberOfParameters')->willReturn(0);
        $reflFn->expects($this->never())->method('getParameters');

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $reflectionFactory->expects($this->once())->method('reflectFunction')
            ->with($this->identicalTo($function))
            ->willReturn($reflFn);

        $provider = new ListenerProvider($reflectionFactory);
        $provider->withListener($function);
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage Invalid event listener: No type hint for parameter $foo
     */
    public function testErrorNoParameterHint()
    {
        $function = function($a) {};

        $reflParam = $this->createMock(ReflectionParameter::class);
        $reflParam->expects($this->once())->method('getType')->willReturn(null);
        $reflParam->expects($this->once())->method('getName')->willReturn('foo');

        $reflFn = $this->createMock(ReflectionFunction::class);
        $reflFn->expects($this->once())->method('getNumberOfParameters')->willReturn(1);
        $reflFn->expects($this->once())->method('getParameters')->willReturn([$reflParam]);

        $reflectionFactory = $this->createMock(ReflectionFactory::class);
        $reflectionFactory->expects($this->once())->method('reflectFunction')
            ->with($this->identicalTo($function))
            ->willReturn($reflFn);

        $provider = new ListenerProvider($reflectionFactory);
        $provider->withListener($function);
    }
}
