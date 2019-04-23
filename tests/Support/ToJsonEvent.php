<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher\Tests\Support;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Test event for json serialize
 * @internal
 */
class ToJsonEvent
{
    /** @var mixed */
    protected $payload;

    public function __construct($payload = null)
    {
        $this->payload = $payload;
    }

    public function setPayload($payload): void
    {
        $this->payload = $payload;
    }

    public function getPayload()
    {
        return $this->payload;
    }
}
