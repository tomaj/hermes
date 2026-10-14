<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Tomaj\Hermes\EmitterInterface;
use Tomaj\Hermes\MessageInterface;

final class UserControllerTest extends TestCase
{
    public function testRegistrationEmitsUserRegistered(): void
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
        $this->app->instance(EmitterInterface::class, $emitter);

        $this->postJson('/users', ['email' => 'jane@example.com'])->assertCreated();
    }

    public function testInvalidEmailEmitsNothing(): void
    {
        $emitter = $this->createMock(EmitterInterface::class);
        $emitter->expects(self::never())->method('emit');
        $this->app->instance(EmitterInterface::class, $emitter);

        $this->postJson('/users', ['email' => 'not-an-email'])->assertUnprocessable();
    }
}
