<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTimeInterface;

interface ShutdownInterface
{
    /**
     * Basic shutdown function.
     *
     * You have to return true or false if hermes worker should shutdown.
     * This method is called from Dispatcher always after messages were processed.
     *
     * @param DateTimeInterface $startTime When the worker started. Dispatcher passes a DateTimeImmutable;
     *                                     do not rely on being able to modify it.
     */
    public function shouldShutdown(DateTimeInterface $startTime): bool;

    /**
     * Initiate shutdown.
     *
     * This function performs necessary operations required to shutdown Hermes through used implementation.
     *
     * @param DateTimeInterface|null $shutdownTime (Optional) When Hermes should shut down. If null, current datetime should be used.
     */
    public function shutdown(?DateTimeInterface $shutdownTime = null): bool;
}
