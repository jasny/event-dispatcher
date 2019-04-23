<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher\Tests\Support;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Test event for before save
 * @internal
 */
class BeforeSaveEvent implements StoppableEventInterface
{
    /** @var object */
    protected $emitter;

    /** @var mixed */
    protected $payload;

    public function __construct(object $emitter, $payload = null)
    {
        $this->emitter = $emitter;
        $this->payload = $payload;
    }

    public function getEmitter(): object
    {
        return $this->subject;
    }

    public function setPayload($payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
