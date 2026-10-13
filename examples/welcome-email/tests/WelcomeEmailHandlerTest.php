<?php

declare(strict_types=1);

namespace App\Tests;

use App\WelcomeEmailHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tomaj\Hermes\Message;

final class WelcomeEmailHandlerTest extends TestCase
{
    public function testSendsTheWelcomeEmail(): void
    {
        $mailer = new class implements MailerInterface {
            /** @var list<RawMessage> */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };

        $handler = new WelcomeEmailHandler($mailer);
        $result = $handler->handle(new Message('user.registered', ['email' => 'jane@example.com', 'name' => 'Jane']));

        self::assertTrue($result);
        self::assertCount(1, $mailer->sent);
        $email = $mailer->sent[0];
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('jane@example.com', $email->getTo()[0]->getAddress());
        self::assertStringContainsString('Hi Jane', (string) $email->getTextBody());
    }
}
