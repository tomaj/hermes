<?php

namespace Tomaj\Hermes\Test\Handler;

use RuntimeException;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Handler\HandlerInterface;

class ExceptionHandler implements HandlerInterface
{
    public function handle(MessageInterface $message)
    {
        throw new RuntimeException('Error in handler');
    }
}
