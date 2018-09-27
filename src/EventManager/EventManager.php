<?php

declare(strict_types=1);

namespace Jasny\Entity\Trigger;

use Jasny\Entity\EntityInterface;
use Jasny\Entity\EventHandler\EventHandlerInterface;
use function Jasny\iterable_expect_type;

/**
 * Service to manage triggers and associated event handlers.
 * @immutable
 */
class EventManager implements EventManagerInterface
{
    /**
     * @var array
     */
    protected $triggerSets = [];

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;


    /**
     * Class constructor
     *
     * @param EventDispatcher $dispatcher
     */
    public function __construct(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Get the event dispatcher.
     *
     * @return EventDispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }


    /**
     * Check if a trigger set is defined.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this->triggerSets[$name]);
    }

    /**
     * Get a trigger set.
     *
     * @param string $name
     * @return callable[]
     */
    public function get(string $name): array
    {
        return $this->triggerSets[$name] ?? [];
    }


    /**
     * Add a trigger set.
     *
     * @param string                                      $name
     * @param iterable|EventHandlerInterface[]|\Closure[] $triggers
     * @return static
     */
    public function with(string $name, iterable $triggers): self
    {
        $triggerSet = iterable_to_array(iterable_expect_type(
            $triggers,
            [EventHandlerInterface::class|\Closure::class],
            \InvalidArgumentException::class,
            "Unable to add '$name' triggers: Expected all triggers to be %2\$s, %1\$s given"
        ));

        $clone = clone $this;
        $clone->triggerSets[$name] = $triggerSet;
        $clone->dispatcher = $this->dispatcher->withAll($triggerSet);

        return $clone;
    }

    /**
     * Remove trigger set.
     *
     * @param string $name
     * @return static
     */
    public function without(string $name): self
    {
        if (!isset($this->triggerSets[$name])) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->triggerSets[$name]);
        $clone->dispatcher = $this->dispatcher->withoutAll($this->triggerSets[$name]);

        return $clone;
    }
}
