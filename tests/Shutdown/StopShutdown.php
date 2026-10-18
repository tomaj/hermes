<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTimeInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

class StopShutdown implements ShutdownInterface
{
    private ?int $eventsStop = null;

    #[\Override]
    public function shouldShutdown(DateTimeInterface $startTime): bool
    {
        if ($this->eventsStop === null) {
            return false;
        }
        if ($this->eventsStop === 1) {
            return true;
        }
        $this->eventsStop--;
        return false;
    }

    #[\Override]
    public function shutdown(?DateTimeInterface $shutdownTime = null): bool
    {
        $this->eventsStop = 1;
        return true;
    }
}
