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
        $this->assertEquals('new-user', $message->getType());
        $this->assertEquals(['hello' => 'kitty'], $message->getPayload());
    }

    public function testMessageWithoutPayload(): void
    {
        $message = new Message('asdssd');
        $this->assertEquals('asdssd', $message->getType());
        $this->assertEquals(null, $message->getPayload());
    }

    public function testMessageWithScheduleAt(): void
    {
        $created = microtime(true);
        $executeAt = microtime(true);
        $message = new Message('asdsd', ['a' => 'b'], '123', $created, $executeAt);
        $this->assertEquals($created, $message->getCreated());
        $this->assertEquals($executeAt, $message->getExecuteAt());
    }
}
