<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use DateTime;
use Tomaj\Hermes\Restart\RestartInterface;

class CustomRestart implements RestartInterface
{
    private $dateTime;

    public function __construct(DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    public function shouldRestart(DateTime $startTime): bool
    {
        return $this->dateTime > $startTime;
    }

    public function restart(DateTime $restartTime = null): bool
    {
        $this->dateTime = $restartTime ?? new DateTime();
        return true;
    }
}
