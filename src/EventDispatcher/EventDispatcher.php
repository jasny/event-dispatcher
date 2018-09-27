<?php

declare(strict_types=1);

namespace Jasny\Entity\Trigger;

use Jasny\Entity\EntityInterface;

/**
 * Class EventDispatcher
 */
class EventDispatcher
{
    /**
     * @var array
     */
    protected $triggers = [];


    /**
     * Bind a callback for before an event.
     *
     * @param string   $event
     * @param callable $callback
     * @return $this
     */
    public function on(string $event, callable $callback): self
    {
        $this->addTrigger($event, $callback);

        return $this;
    }

    /**
     * Trigger before an event.
     *
     * @param string $event
     * @param mixed  $subject
     * @param mixed  $payload
     * @return mixed
     */
    public function trigger(string $event, mixed $subject, $payload = null)
    {
        $callbacks = $this->getTriggers($event);

        foreach ($callbacks as $callback) {
            $payload = call_user_func($callback, $this, $payload);
        }

        return $payload;
    }
}
