<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Message;

/**
 * Class MessageTest
 *
 * @package Tomaj\Hermes\Test
 * @covers \Tomaj\Hermes\Message
 */
class MessageTest extends TestCase
{
    public function testSimpleMessage(): void
    {
        $message = new Message('new-user', ['hello' => 'kitty']);
        self::assertSame('new-user', $message->getType());
        self::assertSame(['hello' => 'kitty'], $message->getPayload());
    }

    public function testMessageWithoutPayload(): void
    {
        $message = new Message('asdssd');
        self::assertSame('asdssd', $message->getType());
        self::assertNull($message->getPayload());
    }

    public function testMessageWithScheduleAt(): void
    {
        $created = microtime(true);
        $executeAt = microtime(true);
        $message = new Message('asdsd', ['a' => 'b'], '123', $created, $executeAt);
        self::assertSame($created, $message->getCreated());
        self::assertSame($executeAt, $message->getExecuteAt());
    }
}
