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
        // Tracy writes exception--*.html here; keep it out of the working tree
        Debugger::enable(Debugger::Detect, sys_get_temp_dir());
        throw new RuntimeException('Error in handler');
    }
}
