<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use DateTimeImmutable;
use DateTimeInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

class CustomShutdown implements ShutdownInterface
{
    public function __construct(private DateTimeInterface $dateTime) {}

    #[\Override]
    public function shouldShutdown(DateTimeInterface $startTime): bool
    {
        return $this->dateTime > $startTime;
    }

    #[\Override]
    public function shutdown(?DateTimeInterface $shutdownTime = null): bool
    {
        $this->dateTime = $shutdownTime ?? new DateTimeImmutable();
        return true;
    }
}
