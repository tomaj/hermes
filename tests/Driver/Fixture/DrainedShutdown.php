<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver\Fixture;

use Closure;
use DateTimeInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

/**
 * Stops a worker once the counter reports nothing left, or after a deadline.
 */
final readonly class DrainedShutdown implements ShutdownInterface
{
    private float $deadline;

    /**
     * @param Closure(): int $remaining
     */
    public function __construct(
        private Closure $remaining,
        float $seconds = 20.0,
    ) {
        $this->deadline = microtime(true) + $seconds;
    }

    #[\Override]
    public function shouldShutdown(DateTimeInterface $startTime): bool
    {
        return ($this->remaining)() === 0 || microtime(true) > $this->deadline;
    }

    #[\Override]
    public function shutdown(?DateTimeInterface $shutdownTime = null): bool
    {
        return true;
    }
}
