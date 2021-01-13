<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use DateTime;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

class CustomShutdown implements ShutdownInterface
{
    /** @var DateTime */
    private $dateTime;

    public function __construct(DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    public function shouldShutdown(DateTime $startTime): bool
    {
        return $this->dateTime > $startTime;
    }

    public function shutdown(DateTime $shutdownTime = null): bool
    {
        $this->dateTime = $shutdownTime ?? new DateTime();
        return true;
    }
}
