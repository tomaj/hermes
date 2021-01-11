<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use DateTime;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

trait ShutdownTrait
{
    /** @var ShutdownInterface */
    private $shutdown;

    /** @var DateTime */
    private $startTime;

    public function setShutdown(ShutdownInterface $shutdown)
    {
        $this->shutdown = $shutdown;
        $this->startTime = new DateTime();
    }

    private function shouldShutdown(): bool
    {
        return $this->shutdown !== null && $this->shutdown->shouldShutdown($this->startTime);
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
