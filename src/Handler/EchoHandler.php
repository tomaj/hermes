<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

final class EchoHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        echo "Received message: #{$message->getId()} (type {$message->getType()})\n";
        $payload = json_encode($message->getPayload());
        echo "Payload: {$payload}\n";
        return true;
    }
}
