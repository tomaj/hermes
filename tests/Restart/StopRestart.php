<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Restart;

use DateTime;
use Tomaj\Hermes\Restart\RestartInterface;

class StopRestart implements RestartInterface
{
    private static $eventsStop;

    public function shouldRestart(DateTime $startTime): bool
    {
        if (self::$eventsStop === 1) {
            return true;
        }
        self::$eventsStop--;
        return false;
    }

    public function restart(DateTime $restartTime = null): bool
    {
        self::$eventsStop = 1;
        return true;
    }
}
