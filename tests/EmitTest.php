<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Test\Driver\DummyDriver;

/**
 * Class EmitTest
 *
 * @package Tomaj\Hermes\Test
 * @covers \Tomaj\Hermes\Emitter
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class EmitTest extends TestCase
{
    public function testEmitWithDummyDriver(): void
    {
        $driver = new DummyDriver();
        $emitter = new Emitter($driver);

        self::assertNull($driver->getMessage());

        $emitter->emit(new Message('event-type', ['content']));

        $message = $driver->getMessage();
        self::assertInstanceOf(Message::class, $message);
        self::assertSame('event-type', $message->getType());
        self::assertSame(['content'], $message->getPayload());
    }
}
