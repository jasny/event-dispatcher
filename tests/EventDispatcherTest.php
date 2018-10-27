<?php

namespace Jasny\EventDispatcher\Tests;

use Jasny\EventDispatcher\EventDispatcher;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    public function testOn()
    {
        $base = new EventDispatcher;

        $dispatcher = $base
            ->on('before-save', function($subject, $payload) {
                $payload['bio'] = $payload['bio'] ?? "$subject->name <{$subject->email}> just arrived";

                return $payload;
            })
            ->on('before-save.censor', function($subject, $payload) {
                $payload['bio'] = strtr($payload['bio'], [$subject->email => '***@***.***']);

                return $payload;
            })
            ->on('json.censor', function($subject, $payload) {
                return array_without($payload, ['password']);
            })
            ->on('sync', function($subject, $payload) {
                return $payload + 10;
            })
            ->on('sync', function($subject, $payload) {
                return $payload + 20;
            });

        $this->assertInstanceOf(EventDispatcher::class, $base);
        $this->assertNotSame($base, $dispatcher);

        return $dispatcher;
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid event name '*.foo': illegal character '*'
     */
    public function testOnInvalidName()
    {
        (new EventDispatcher)->on('*.foo', function() {});
    }

    public function offProvider()
    {
        return [
            ['before-save.censor', 4],
            ['before-save', 3],
            ['*.censor', 3],
        ];
    }

    /**
     * @depends testOn
     * @dataProvider offProvider
     */
    public function testOff(string $event, int $nrHandlers, EventDispatcher $base)
    {
        $dispatcher = $base->off($event);

        $this->assertInstanceOf(EventDispatcher::class, $base);
        $this->assertNotSame($base, $dispatcher);

        $this->assertAttributeCount($nrHandlers, 'triggers', $dispatcher);

        $this->assertSame($dispatcher, $dispatcher->off('before-save.censor'));
    }


    /**
     * @depends testOn
     */
    public function testTriggerBeforeSave(EventDispatcher $dispatcher)
    {
        $payload = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'age' => 37
        ];
        $subject = (object)(['foo' => 42] + $payload);

        $result = $dispatcher->trigger('before-save', $subject, $payload);

        $expected = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'age' => 37,
            'bio' => 'John Doe <***@***.***> just arrived'
        ];
        $this->assertEquals($expected, $result);

        $this->assertEquals((object)(['foo' => 42] + $payload), $subject);
    }
}
