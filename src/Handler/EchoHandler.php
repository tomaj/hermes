<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Handler;

use Tomaj\Hermes\MessageInterface;

/**
 * @final since 5.1, final in 6.0: wrap the class or implement its interface instead of extending it
 */
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
        echo "Payload: {$payload}\n";
        return true;
    }
}
