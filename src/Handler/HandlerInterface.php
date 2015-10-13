<?php

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

interface HandlerInterface
{
    public function handle(MessageInterface $message);
}
