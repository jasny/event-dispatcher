<?php

namespace Jasny\ListenerProvider\Tests;

use Jasny\EventDispatcher\Event;
use Jasny\EventDispatcher\ListenerProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * @covers \Jasny\EventDispatcher\ListenerProvider
 */
class ListenerProviderTest extends TestCase
{
    public function testOn()
    {
        $base = new ListenerProvider();

        $provider = $base
            ->on('before-save', function(Event $event) {
                $subject = $event->getSubject();
                $payload = $event->getPayload();

                $payload['bio'] = $payload['bio'] ?? "$subject->name <{$subject->email}> just arrived";
                $event->setPayload($payload);
            })
            ->on('before-save.censor', function(Event $event) {
                $subject = $event->getSubject();
                $payload = $event->getPayload();

                $payload['bio'] = strtr($payload['bio'], [$subject->email => '***@***.***']);
                $event->setPayload($payload);
            })
            ->on('json.censor', function(Event $event) {
                $payload = $event->getPayload();
                unset($payload['password']);

                $event->setPayload($payload);
            })
            ->on('sync', function(Event $event) {
                $event->setPayload($event->getPayload() + 10);
            })
            ->on('sync', function(Event $event) {
                $event->setPayload($event->getPayload() + 20);
            });

        $this->assertInstanceOf(ListenerProvider::class, $provider);
        $this->assertNotSame($base, $provider);

        return $provider;
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid event name '*.foo': illegal character '*'
     */
    public function testOnInvalidName()
    {
        (new ListenerProvider)->on('*.foo', function() {});
    }


    /**
     * @depends testOn
     */
    public function testGetListenersForBeforeSave(ListenerProvider $provider)
    {
        $subject = (object)[
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
        ];

        $listeners = $provider->getListenersForEvent(new Event('before-save'));
        $this->assertCount(2, $listeners);

        $event = $this->createMock(Event::class);
        $event->expects($this->any())->method('getSubject')->willReturn($subject);
        $event->expects($this->exactly(2))->method('getPayload')
            ->willReturnOnConsecutiveCalls([], ['bio' => "John Doe <john.doe@example.com> just arrived"]);
        $event->expects($this->any())->method('setPayload')
            ->withConsecutive(
                [['bio' => "John Doe <john.doe@example.com> just arrived"]],
                [['bio' => "John Doe <***@***.***> just arrived"]]
            );

        ($listeners[0])($event);
        ($listeners[1])($event);
    }

    /**
     * @depends testOn
     */
    public function testGetListenersForJson(ListenerProvider $provider)
    {
        $listeners = $provider->getListenersForEvent(new Event('json'));
        $this->assertCount(1, $listeners);

        $event = $this->createMock(Event::class);
        $event->expects($this->once())->method('getPayload')
            ->willReturn(['username' => 'john', 'password' => '12345']);
        $event->expects($this->once())->method('setPayload')
            ->willReturn(['username' => 'john']);

        ($listeners[0])($event);
    }


    public function offProvider()
    {
        return [
            ['sync', 2, 1, 0],
            ['before-save.censor', 1, 1, 2],
            ['before-save', 0, 1, 2],
            ['*.censor', 1, 0, 2],
        ];
    }

    /**
     * @depends testOn
     * @dataProvider offProvider
     */
    public function testOff(string $event, int $nrBeforeSave, int $nrJson, int $nrSync, ListenerProvider $base)
    {
        $provider = $base->off($event);

        $this->assertInstanceOf(ListenerProvider::class, $base);
        $this->assertNotSame($base, $provider);

        $this->assertCount($nrBeforeSave, $provider->getListenersForEvent(new Event('before-save')));
        $this->assertCount($nrJson, $provider->getListenersForEvent(new Event('json')));
        $this->assertCount($nrSync, $provider->getListenersForEvent(new Event('sync')));
    }

    /**
     * @depends testOn
     */
    public function testOffIdempotent(ListenerProvider $base)
    {
        $provider = $base->off('before-save');

        $this->assertInstanceOf(ListenerProvider::class, $base);
        $this->assertNotSame($base, $provider);

        $this->assertSame($provider, $provider->off('before-save'));
        $this->assertSame($provider, $provider->off('before-save.censor'));
        $this->assertSame($provider, $provider->off('does-not-exist'));
    }

    public function testCustomClass()
    {
        $event = new class() implements StoppableEventInterface {
            public function isPropagationStopped(): bool
            {
                return false;
            }
        };
        $listener = function(object $event) {};

        $provider = (new ListenerProvider)->on(StoppableEventInterface::class, $listener);

        $listeners = $provider->getListenersForEvent($event);

        $this->assertCount(1, $listeners);
        $this->assertSame($listener, $listeners[0]);
    }
}
