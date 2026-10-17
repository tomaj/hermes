<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use DateTimeImmutable;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

trait ShutdownTrait
{
    private ?ShutdownInterface $shutdown = null;

    private ?DateTimeImmutable $startTime = null;

    public function setShutdown(ShutdownInterface $shutdown): void
    {
        $this->shutdown = $shutdown;
        $this->startTime = new DateTimeImmutable();
    }

    private function shouldShutdown(): bool
    {
        if ($this->shutdown === null || $this->startTime === null) {
            return false;
        }
        return $this->shutdown->shouldShutdown($this->startTime);
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
