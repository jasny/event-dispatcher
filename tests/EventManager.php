<?php

namespace Jasny\Entity\Tests\Trigger;

use Jasny\Entity\EntityInterface;
use Jasny\Entity\EventHandler\EventHandlerInterface;
use Jasny\Entity\Trigger\EventManager;
use Jasny\TestHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\Entity\Trigger\EventManager
 */
class TriggerManagerTest extends TestCase
{
    use TestHelper;

    public function testWith()
    {
        $setEmpty = new EventManager();

        $foo = [
            'first' => $this->createMock(EventHandlerInterface::class),
            'second' => function() {}
        ];
        $bar = [
            'first' => function() {}
        ];

        $setFoo = $setEmpty->with('foo', $foo);
        $this->assertInstanceOf(EventManager::class, $setFoo);
        $this->assertNotSame($setEmpty, $setFoo);
        $this->assertAttributeSame(compact('foo'), 'triggers', $setFoo);

        $setFooBar = $setFoo->with('bar', $bar);
        $this->assertInstanceOf(EventManager::class, $setFooBar);
        $this->assertNotSame($setFoo, $setFooBar);
        $this->assertAttributeSame(compact('foo', 'bar'), 'triggers', $setFooBar);

        return $setFooBar;
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unable to add 'foo' trigger(s); Expected Jasny\Entity\EventHandler\EventHandlerInterface or (non-object) callable, got a class@anonymous
     */
    public function testWithNonHandlerCallableObject()
    {
        $setEmpty = new EventManager();

        $invoke = new class() {
            public function __invoke()
            {
            }
        };

        $setEmpty->with('foo', ['first' => $invoke]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Unable to add 'foo' trigger(s); Expected Jasny\Entity\EventHandler\EventHandlerInterface or (non-object) callable, got a string
     */
    public function testWithUncallable()
    {
        $setEmpty = new EventManager();

        $setEmpty->with('foo', ['first' => 'non_existent']);
    }

    /**
     * @depends testWith
     */
    public function testHas(EventManager $set)
    {
        $this->assertTrue($set->has('bar'));
        $this->assertFalse($set->has('non_existing'));
    }

    /**
     * @depends testWith
     * @depends testHas
     */
    public function testWithout(EventManager $setFooBar)
    {
        $setFoo = $setFooBar->without('bar');

        $this->assertNotSame($setFooBar, $setFoo);

        $this->assertTrue($setFoo->has('foo'));
        $this->assertFalse($setFoo->has('bar'));
    }

    /**
     * @depends testWith
     */
    public function testWithoutNonExisting(EventManager $setFooBar)
    {
        $set = $setFooBar->without('non_existing');

        $this->assertSame($setFooBar, $set);
    }

    /**
     * @depends testWith
     */
    public function testGet()
    {
        $bar = [
            'first' => function() {}
        ];

        $setEmpty = new EventManager();
        $setBar = $setEmpty->with('bar', $bar);

        $this->assertSame($bar, $setBar->get('bar'));
    }

    /**
     * @expectedException \OutOfBoundsException
     */
    public function testGetNonExisting()
    {
        $setEmpty = new EventManager();

        $setEmpty->get('non_existing');
    }

    /**
     * @depends testWith
     */
    public function testApplyTo(EventManager $setFooBar)
    {
        $entity = $this->createMock(EntityInterface::class);
        $entity->expects($this->exactly(3))->method('on')
            ->withConsecutive(
                ['first', $this->isInstanceOf(EventHandlerInterface::class)],
                ['second', $this->isInstanceOf(\Closure::class)],
                ['first', $this->isInstanceOf(\Closure::class)]
            )
            ->willReturnSelf();

        $setFooBar->applyTo($entity);
    }
}
