<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Event used by event dispatcher.
 */
class Event implements StoppableEventInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var object|null
     */
    protected $subject;

    /**
     * @var mixed
     */
    protected $payload;

    /**
     * @var bool
     */
    protected $propagationStopped = false;

    /**
     * Event constructor.
     *
     * @param string      $name
     * @param object|null $subject
     * @param mixed       $payload
     */
    public function __construct(string $name, ?object $subject = null, $payload = null)
    {
        if (strpos($name, '*') !== false) {
            throw new \InvalidArgumentException("Invalid event name '$name': illegal character '*'");
        }

        $this->name = $name;
        $this->subject = $subject;
        $this->payload = $payload;
    }


    /**
     * Get the event name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the event subject (typically an object)
     *
     * @return object|null
     */
    public function getSubject(): ?object
    {
        return $this->subject;
    }

    /**
     * Change the event payload.
     *
     * @param mixed $payload
     */
    public function setPayload($payload): void
    {
        $this->payload = $payload;
    }

    /**
     * Get the event payload.
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->payload;
    }


    /**
     * Do call subsequent event listeners.
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * Is propagation stopped?
     *
     * @return bool
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
