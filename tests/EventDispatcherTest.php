<?php

namespace Jasny\EventDispatcher\Tests;

use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;
use Jasny\EventDispatcher\Tests\Support\BeforeSaveEvent;
use Jasny\EventDispatcher\Tests\Support\SumEvent;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\EventDispatcher\EventDispatcher
 */
class EventDispatcherTest extends TestCase
{
    public function testWithListenerProvider()
    {
        $listenerProvider = $this->createMock(ListenerProvider::class);
        $dispatcher = new EventDispatcher($listenerProvider);

        $newListenerProvider = $this->createMock(ListenerProvider::class);
        $newDispatcher = $dispatcher->withListenerProvider($newListenerProvider);

        $this->assertInstanceOf(EventDispatcher::class, $newDispatcher);
        $this->assertNotSame($dispatcher, $newDispatcher);
        $this->assertSame($newListenerProvider, $newDispatcher->getListenerProvider());

        // Idempotent
        $this->assertSame($newDispatcher, $newDispatcher->withListenerProvider($newListenerProvider));

        // Immutable, old object is unchanged.
        $this->assertSame($listenerProvider, $dispatcher->getListenerProvider());
    }

    public function testDispatch()
    {
        $event = new SumEvent();
        $event->add(12);

        $listenerProvider = $this->createMock(ListenerProvider::class);
        $listenerProvider->expects($this->once())->method('getListenersForEvent')
            ->with($this->identicalTo($event))
            ->willReturn([
                function(SumEvent $event) {
                    $event->add(20);
                },
                function(SumEvent $event) {
                    $event->add(10);
                },
            ]);

        $dispatcher = new EventDispatcher($listenerProvider);
        $ret = $dispatcher->dispatch($event);

        $this->assertSame($event, $ret);
        $this->assertEquals(42, $event->getTotal());
    }

    public function testDispatchStopPropegation()
    {
        $event = $this->createMock(BeforeSaveEvent::class);
        $event->expects($this->exactly(2))->method('isPropagationStopped')
            ->willReturnOnConsecutiveCalls(false, true);
        $event->expects($this->once())->method('setPayload')->with(1);

        $listenerProvider = $this->createMock(ListenerProvider::class);
        $listenerProvider->expects($this->once())->method('getListenersForEvent')
            ->with($this->identicalTo($event))
            ->willReturn([
                function($arg) use ($event) {
                    $this->assertSame($event, $arg);
                    $event->setPayload(1);
                },
                function($arg) use ($event) {
                    $this->assertSame($event, $arg);
                    $event->setPayload(2);
                },
                function($arg) use ($event) {
                    $this->assertSame($event, $arg);
                    $event->setPayload(3);
                },
            ]);

        $dispatcher = new EventDispatcher($listenerProvider);
        $ret = $dispatcher->dispatch($event);

        $this->assertSame($event, $ret);
    }
}
