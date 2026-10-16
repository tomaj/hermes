<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Handler;

use RuntimeException;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;
use Tracy\Debugger;

class ExceptionHandler implements HandlerInterface
{
    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        Debugger::enable(Debugger::Detect, __DIR__);
        throw new RuntimeException('Error in handler');
    }
}
