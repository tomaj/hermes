<?php

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

class EchoHandler implements HandlerInterface
{
    /**
     * {@inheritdoc}
     */
    public function handle(MessageInterface $message): bool
    {
        echo "Received message: #{$message->getId()} (type {$message->getType()})\n";
        $payload = json_encode($message->getPayload());
        echo "Payload: {$payload}\n";
        return true;
    }
}
