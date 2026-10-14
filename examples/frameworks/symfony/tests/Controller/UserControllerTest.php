<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\MessageInterface;

final class UserControllerTest extends WebTestCase
{
    public function testRegistrationEmitsUserRegistered(): void
    {
        $client = static::createClient();

        // Mock the interface: Emitter is final from Hermes 6.0.
        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects(self::once())
            ->method('emit')
            ->with(self::callback(
                static fn(MessageInterface $message): bool => $message->getType() === 'user.registered'
                    && $message->getPayload() === ['email' => 'jane@example.com'],
            ))
            ->willReturnSelf();
        static::getContainer()->set(EmitterInterface::class, $emitter);

        $client->jsonRequest('POST', '/users', ['email' => 'jane@example.com']);

        self::assertResponseStatusCodeSame(201);
    }

    public function testInvalidEmailEmitsNothing(): void
    {
        $client = static::createClient();
        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects(self::never())->method('emit');
        static::getContainer()->set(EmitterInterface::class, $emitter);

        $client->jsonRequest('POST', '/users', ['email' => 'not-an-email']);

        self::assertResponseStatusCodeSame(422);
    }
}
