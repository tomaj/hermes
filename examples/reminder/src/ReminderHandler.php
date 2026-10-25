<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

final class ReminderHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function __construct(private MailerInterface $mailer) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload() ?? [];

        // The message was written a day ago. Check that it still applies.
        if (!$this->stillBooked((int) $payload['reservation_id'])) {
            return true;
        }

        $email = new Email();
        $email
            ->from('bookings@example.com')
            ->to($payload['email'])
            ->subject('See you tomorrow')
            ->text('This is a reminder of your reservation tomorrow.');
        $this->mailer->send($email);

        return true;
    }

    private function stillBooked(int $reservationId): bool
    {
        return true; // replace with a database lookup
    }
}
