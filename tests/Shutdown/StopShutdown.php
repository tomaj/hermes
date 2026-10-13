<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTime;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

class StopShutdown implements ShutdownInterface
{
    private static ?int $eventsStop = null;

    #[\Override]
    public function shouldShutdown(DateTime $startTime): bool
    {
        if (self::$eventsStop === null) {
            return false;
        }
        if (self::$eventsStop === 1) {
            return true;
        }
        self::$eventsStop--;
        return false;
    }

    #[\Override]
    public function shutdown(?DateTime $shutdownTime = null): bool
    {
        self::$eventsStop = 1;
        return true;
    }
}
