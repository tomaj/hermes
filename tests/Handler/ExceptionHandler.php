<?php

namespace Tomaj\Hermes\Test\Handler;

use RuntimeException;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tracy\Debugger;

class ExceptionHandler implements HandlerInterface
{
    public function handle(MessageInterface $message): bool
    {
    	Debugger::enable(Debugger::DETECT, __DIR__);
        throw new RuntimeException('Error in handler');
    }
}
