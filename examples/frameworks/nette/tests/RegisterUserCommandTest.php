<?php

declare(strict_types=1);

namespace Tests;

use App\Console\RegisterUserCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\MessageInterface;

final class RegisterUserCommandTest extends TestCase
{
    public function testEmitsUserRegistered(): void
    {
        // Mock the interface: Emitter is final from Hermes 6.0.
        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects(self::once())
            ->method('emit')
            ->with(self::callback(
                static fn(MessageInterface $message): bool => $message->getType() === 'user.registered'
                    && $message->getPayload() === ['email' => 'jane@example.com'],
            ))
            ->willReturnSelf();

        $tester = new CommandTester(new RegisterUserCommand($emitter));

        self::assertSame(0, $tester->execute(['email' => 'jane@example.com']));
    }
}
