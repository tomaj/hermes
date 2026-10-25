<?php

declare(strict_types=1);

namespace App\Hermes;

use Psr\Log\LoggerInterface;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

final class UserRegisteredHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function __construct(private readonly LoggerInterface $logger) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        $email = $message->getPayload()['email'] ?? null;
        if (!is_string($email)) {
            return false; // a broken message stays broken, do not retry it
        }

        // Send the welcome email here. Throw an exception to retry later.
        $this->logger->info("Welcome email sent to {$email}", ['message_id' => $message->getId()]);

        return true;
    }
}
