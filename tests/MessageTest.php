<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit\Framework\Attributes\DataProvider;
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

    private const string UUID4 = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testOmittedIdGeneratesAFreshUuid4(): void
    {
        $first = new Message('job');
        $second = new Message('job');

        self::assertMatchesRegularExpression(self::UUID4, $first->getId());
        self::assertNotSame($first->getId(), $second->getId());
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function emptyIds(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
    }

    #[DataProvider('emptyIds')]
    public function testNullAndEmptyIdGenerateAFreshUuid4(?string $id): void
    {
        $first = new Message('job', [], $id);
        $second = new Message('job', [], $id);

        self::assertMatchesRegularExpression(self::UUID4, $first->getId());
        self::assertNotSame($first->getId(), $second->getId());
    }

    public function testAGivenIdIsKept(): void
    {
        self::assertSame('order-42', new Message('job', [], 'order-42')->getId());
    }

    public function testZeroIsAnIdNotAnEmptyValue(): void
    {
        self::assertSame('0', new Message('job', [], '0')->getId());
    }
}
