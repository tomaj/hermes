<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

final class WelcomeEmailHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function __construct(private MailerInterface $mailer) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload() ?? [];
        $name = ($payload['name'] ?? '') !== '' ? $payload['name'] : 'there';

        $email = new Email();
        $email
            ->from('hello@example.com')
            ->to($payload['email'])
            ->subject('Welcome aboard!')
            ->text("Hi {$name},\n\nthanks for signing up.");

        // A TransportException here (SMTP down, timeout) makes Hermes send a retry copy.
        $this->mailer->send($email);

        return true;
    }

    public function maxRetry(): int
    {
        return 5;
    }
}
