<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Shutdown;

use DateTime;

interface ShutdownInterface
{
    /**
     * Basic shutdown function.
     *
     * You have to return true or false if hermes worker should shutdown.
     * This method is called from Dispatcher always after messages were processed
     *
     * @param DateTime $startTime
     * @return bool
     */
    public function shouldShutdown(DateTime $startTime): bool;

    /**
     * Initiate shutdown.
     *
     * This function performs necessary operations required to shutdown Hermes through used implementation.
     *
     * @param DateTime|null $shutdownTime (Optional) DateTime when should be Hermes shutdown. If null, current datetime should be used.
     * @return bool
     */
    public function shutdown(DateTime $shutdownTime = null): bool;
}
