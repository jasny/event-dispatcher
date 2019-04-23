<?php

declare(strict_types=1);

namespace Jasny\EventDispatcher\Tests\Support;

/**
 * Test event
 * @internal
 */
class SumEvent
{
    /** @var mixed */
    protected $total = 0;

    public function add(int $total): void
    {
        $this->total += $total;
    }

    public function getTotal()
    {
        return $this->total;
    }
}
