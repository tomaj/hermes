<?php

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\Message;
use Closure;

interface DriverInterface
{
    public function send(Message $message);

    public function wait(Closure $callback);
}
