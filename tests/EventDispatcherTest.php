<?php

namespace Jasny\EventDispatcher\Tests;

use Jasny\EventDispatcher\Event;
use Jasny\EventDispatcher\EventDispatcher;
use Jasny\EventDispatcher\ListenerProvider;
use PHPUnit\Framework\TestCase;

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
        $event = $this->createMock(\stdClass::class); // Doesn't need to be an Event object
        $event->answer = 31;
        $event->expects($this->never())->method($this->anything());

        $listenerProvider = $this->createMock(ListenerProvider::class);
        $listenerProvider->expects($this->once())->method('getListenersForEvent')
            ->with($this->identicalTo($event))
            ->willReturn([
                function(object $event) {
                    $event->answer++;
                },
                function(object $event) {
                    $event->answer += 10;
                },
            ]);

        $dispatcher = new EventDispatcher($listenerProvider);
        $ret = $dispatcher->dispatch($event);

        $this->assertSame($event, $ret);
        $this->assertEquals(42, $event->answer);
    }

    public function testDispatchStopPropegation()
    {
        $event = $this->createMock(Event::class);
        $event->expects($this->exactly(2))->method('isPropagationStopped')
            ->willReturnOnConsecutiveCalls(false, true);
        $event->expects($this->once())->method('setPayload')->with(1);

        $listenerProvider = $this->createMock(ListenerProvider::class);
        $listenerProvider->expects($this->once())->method('getListenersForEvent')
            ->with($this->identicalTo($event))
            ->willReturn([
                function(Event $event) {
                    $event->setPayload(1);
                },
                function(Event $event) {
                    $event->setPayload(2);
                },
                function(Event $event) {
                    $event->setPayload(3);
                },
            ]);

        $dispatcher = new EventDispatcher($listenerProvider);
        $ret = $dispatcher->dispatch($event);

        $this->assertSame($event, $ret);
    }
}
