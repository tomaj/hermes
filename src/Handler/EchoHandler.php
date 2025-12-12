<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

class EchoHandler implements HandlerInterface
{
    use RetryTrait;

    /**
     * {@inheritdoc}
     */
    public function handle(MessageInterface $message): bool
    {
        echo "Received message: #{$message->getId()} (type {$message->getType()})\n";
        $payload = json_encode($message->getPayload());
        echo sprintf('Payload: %s%s', $payload, PHP_EOL);
        return true;
    }
}
