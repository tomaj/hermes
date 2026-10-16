<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;

/**
 * How MessageSerializer writes each field and reads optional or loosely typed ones.
 *
 * @covers \Tomaj\Hermes\MessageSerializer
 * @covers \Tomaj\Hermes\Message
 */
class MessageSerializerFieldsTest extends TestCase
{
    public function testSerializeWritesEveryField(): void
    {
        $json = new MessageSerializer()->serialize(new Message('type', ['a' => 'b'], 'id-1', 1700000000.5, 1700000100.25, 3));

        self::assertSame(
            ['message' => [
                'id' => 'id-1',
                'type' => 'type',
                'created' => 1700000000.5,
                'payload' => ['a' => 'b'],
                'execute_at' => 1700000100.25,
                'retries' => 3,
            ]],
            json_decode($json, true),
        );
    }

    public function testOptionalFieldsDefaultWhenMissing(): void
    {
        $message = new MessageSerializer()->unserialize('{"message":{"type":"type","id":"id-1","created":1700000000}}');

        self::assertNull($message->getPayload());
        self::assertNull($message->getExecuteAt());
        self::assertSame(0, $message->getRetries());
    }

    public function testNumericStringsAreCast(): void
    {
        $message = new MessageSerializer()->unserialize(
            '{"message":{"type":"type","id":"id-1","created":"1700000000","execute_at":"1700000100.25","retries":"3"}}',
        );

        self::assertSame(1700000000.0, $message->getCreated());
        self::assertSame(1700000100.25, $message->getExecuteAt());
        self::assertSame(3, $message->getRetries());
    }

    public function testNonStringIdIsRejected(): void
    {
        $this->expectException(SerializeException::class);

        new MessageSerializer()->unserialize('{"message":{"type":"type","id":5,"created":1700000000}}');
    }
}
