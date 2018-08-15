<?php

namespace Tomaj\Hermes\Restart;

use DateTime;

interface RestartInterface
{
    /**
     * Basic restart function.
     *
     * You have to return true or false if hermes worker should restart.
     * This method is called from Dispatcher always after messages was procesed
     *
     * @param DateTime $startTime
     * @return bool
     */
    public function shouldRestart(DateTime $startTime): bool;
}
