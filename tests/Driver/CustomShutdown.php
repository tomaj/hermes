<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use DateTime;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

class CustomShutdown implements ShutdownInterface
{
    public function __construct(private DateTime $dateTime) {}

    #[\Override]
    public function shouldShutdown(DateTime $startTime): bool
    {
        return $this->dateTime > $startTime;
    }

    #[\Override]
    public function shutdown(?DateTime $shutdownTime = null): bool
    {
        $this->dateTime = $shutdownTime ?? new DateTime();
        return true;
    }
}
