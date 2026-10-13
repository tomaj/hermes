<?php

declare(strict_types=1);

namespace App;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

final class PrintHandler implements HandlerInterface
{
    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        // Replace with the real work for each type.
        printf("[%s] %s %s\n", date('H:i:s'), $message->getType(), json_encode($message->getPayload()));

        return true;
    }
}
