<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Restart;

use DateTime;

interface RestartInterface
{
    /**
     * Basic restart function.
     *
     * You have to return true or false if hermes worker should restart.
     * This method is called from Dispatcher always after messages were processed
     *
     * @param DateTime $startTime
     * @return bool
     */
    public function shouldRestart(DateTime $startTime): bool;

    /**
     * Initiate restart.
     *
     * This function performs necessary operations required to restart Hermes through used implementation.
     *
     * @param DateTime $restartTime (Optional) DateTime when should be Hermes restarted. If null, current datetime should be used.
     * @return bool
     */
    public function restart(DateTime $restartTime = null): bool;
}
