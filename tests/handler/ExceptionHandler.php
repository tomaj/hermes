<?php

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

class ExceptionHandler implements HandlerInterface
{
    public function handle(MessageInterface $message)
    {
        throw new \RuntimeException('Error in handler');
    }
}
