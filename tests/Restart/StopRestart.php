<?php

namespace Tomaj\Hermes\Test\Restart;

use DateTime;
use Tomaj\Hermes\Restart\RestartInterface;

class StopRestart implements RestartInterface
{
    private $eventsStop;

    public function __construct($eventsStop = 1)
    {
        $this->eventsStop = $eventsStop;
    }

    public function shouldRestart(DateTime $startTime)
    {
        if ($this->eventsStop == 1) {
            return true;
        }
        $this->eventsStop--;
        return false;
    }
}
