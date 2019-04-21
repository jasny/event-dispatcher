<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher;

use Improved\IteratorPipeline\Pipeline;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Event dispatcher.
 * @immutable
 */
class ListenerProvider implements ListenerProviderInterface
{
    /**
     * @var array
     */
    protected $listeners = [];


    /**
     * Bind a handler for an event.
     *
     * @param string   $eventName
     * @param callable $listener
     * @return static
     */
    public function on(string $eventName, callable $listener): self
    {
        if (strpos($eventName, '*') !== false) {
            throw new \InvalidArgumentException("Invalid event name '$eventName': illegal character '*'");
        }

        $clone = clone $this;
        $clone->listeners[] = ['event' => $eventName, 'listener' => $listener];

        return $clone;
    }

    /**
     * Unbind a handler of an event.
     *
     * @param string $eventName  Event name, optionally with wildcards
     * @return $this
     */
    public function off(string $eventName): self
    {
        $listeners = Pipeline::with($this->listeners)
            ->filter(function ($trigger) use ($eventName) {
                return !fnmatch($eventName, $trigger['event'], FNM_NOESCAPE)
                    && !fnmatch("$eventName.*", $trigger['event'], FNM_NOESCAPE);
            })
            ->values()
            ->toArray();

        if (count($listeners) === count($this->listeners)) {
            return $this;
        }

        $clone = clone $this;
        $clone->listeners = $listeners;

        return $clone;
    }


    /**
     * Get the relevant listeners for the given event
     *
     * @param object $event
     * @return callable[]
     */
    public function getListenersForEvent(object $event): iterable
    {
        $eventName = $event instanceof Event ? $event->getName() : null;

        return Pipeline::with($this->listeners)
            ->filter(function ($trigger) use ($event, $eventName) {
                return is_a($event, $trigger['event'])
                    || $eventName === $trigger['event']
                    || ($eventName !== null && fnmatch("{$eventName}.*", $trigger['event'], FNM_NOESCAPE));
            })
            ->column('listener')
            ->values()
            ->toArray();
    }
}
