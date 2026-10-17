<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTimeInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

/**
 * Stops a worker after a fixed number of shutdown checks or a few seconds,
 * whichever comes first, so a test whose driver never finds a message fails
 * with ShutdownException instead of polling forever. The time limit covers
 * drivers with a refresh interval, which check rarely; it stays below the
 * Infection timeout.
 */
final class PollBudgetShutdown implements ShutdownInterface
{
    private int $checks = 0;

    private readonly float $deadline;

    public function __construct(
        private readonly int $budget = 100,
        float $seconds = 5.0,
    ) {
        $this->deadline = microtime(true) + $seconds;
    }

    #[\Override]
    public function shouldShutdown(DateTimeInterface $startTime): bool
    {
        return ++$this->checks > $this->budget || microtime(true) > $this->deadline;
    }

    #[\Override]
    public function shutdown(?DateTimeInterface $shutdownTime = null): bool
    {
        return true;
    }
}
