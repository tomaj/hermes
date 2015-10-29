<?php

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;
use RuntimeException;

class ExceptionHandler implements HandlerInterface
{
    public function handle(MessageInterface $message)
    {
        throw new RuntimeException('Error in handler');
    }
}
