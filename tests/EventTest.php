<?php

namespace Jasny\EventDispatcher\Tests;

use Jasny\EventDispatcher\Event;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\EventDispatcher\Event
 */
class EventTest extends TestCase
{

    public function testConstruct()
    {
        $subject = (object)[];

        $event = new Event('foo', $subject, ['foo' => 'bar']);

        $this->assertEquals('foo', $event->getName());
        $this->assertSame($subject, $event->getSubject());
        $this->assertEquals(['foo' => 'bar'], $event->getPayload());
    }

    public function testSetPayload()
    {
        $event = new Event('foo', (object)[], ['foo' => 'bar']);
        $event->setPayload(['foo' => 'BAAAR', 'answer' => 42]);

        $this->assertEquals(['foo' => 'BAAAR', 'answer' => 42], $event->getPayload());
    }

    public function testStopPropagation()
    {
        $event = new Event('foo');
        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();
        $this->assertTrue($event->isPropagationStopped());
    }


    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid event name '*.foo': illegal character '*'
     */
    public function testWithInvalidName()
    {
        new Event('*.foo');
    }
}
