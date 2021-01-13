<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use DateTime;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

class StopShutdown implements ShutdownInterface
{
    /** @var int */
    private static $eventsStop;

    public function shouldShutdown(DateTime $startTime): bool
    {
        if (self::$eventsStop === 1) {
            return true;
        }
        self::$eventsStop--;
        return false;
    }

    public function shutdown(DateTime $shutdownTime = null): bool
    {
        self::$eventsStop = 1;
        return true;
    }
}
