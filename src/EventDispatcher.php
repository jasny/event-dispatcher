<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher;

use Improved as i;
use Improved\IteratorPipeline\Pipeline;
use function Jasny\str_contains;

/**
 * Event dispatcher.
 * @immutable
 */
class EventDispatcher
{
    /**
     * @var array
     */
    protected $triggers = [];


    /**
     * Bind a handler for an event.
     *
     * @param string   $event
     * @param callable $handler
     * @return static
     */
    public function on(string $event, callable $handler): self
    {
        if (str_contains($event, '*')) {
            throw new \InvalidArgumentException("Invalid event name '$event': illegal character '*'");
        }

        $clone = clone $this;
        $clone->triggers[] = ['event' => $event, 'handler' => $handler];

        return $clone;
    }

    /**
     * Unbind a handler of an event.
     *
     * @param string $event  Event name, optionally with wildcards
     * @return $this
     */
    public function off(string $event): self
    {
        $triggers = Pipeline::with($this->triggers)
            ->filter(function ($trigger) use ($event) {
                return !fnmatch($event, $trigger['event'], FNM_NOESCAPE)
                    && !fnmatch("$event.*", $trigger['event'], FNM_NOESCAPE);
            })
            ->values()
            ->toArray();

        if (count($triggers) === count($this->triggers)) {
            return $this;
        }

        $clone = clone $this;
        $clone->triggers = $triggers;

        return $clone;
    }


    /**
     * Trigger an event.
     *
     * @param string $event
     * @param object $subject
     * @param mixed  $payload
     * @return mixed
     */
    public function trigger(string $event, $subject, $payload = null)
    {
        return Pipeline::with($this->triggers)
            ->filter(function ($trigger) use ($event) {
                return $event ===  $trigger['event'] || fnmatch("{$event}.*", $trigger['event'], FNM_NOESCAPE);
            })
            ->column('handler')
            ->reduce(function ($payload, $handler) use ($subject) {
                return i\function_call($handler, $subject, $payload);
            }, $payload);
    }
}
