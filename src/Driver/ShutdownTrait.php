<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use DateTime;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

trait ShutdownTrait
{
    private ShutdownInterface $shutdown;

    private DateTime $startTime;

    public function setShutdown(ShutdownInterface $shutdown): void
    {
        $this->shutdown = $shutdown;
        $this->startTime = new DateTime();
    }

    private function shouldShutdown(): bool
    {
        return isset($this->shutdown) && $this->shutdown->shouldShutdown($this->startTime);
    }

    /**
     * @throws ShutdownException
     */
    private function checkShutdown(): void
    {
        if ($this->shouldShutdown()) {
            throw new ShutdownException();
        }
    }
}
