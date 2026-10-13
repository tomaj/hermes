<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;

/**
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @requires extension redis
 */
class RedisSetDriverScheduleTest extends TestCase
{
    public function testDueScheduledMessageIsMovedToQueueWithZrem(): void
    {
        $serialized = new MessageSerializer()->serialize(new Message('due', ['a' => 1], 'id-1', null, microtime(true) - 10));

        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('zRangeByScore')
            ->with('sched', '-inf', self::callback('is_string'), ['limit' => [0, 1]])
            ->willReturn([$serialized]);
        $redis->expects(self::once())->method('zRem')->with('sched', $serialized)->willReturn(1);
        $redis->expects(self::once())->method('sAdd')->with('q', $serialized)->willReturn(1);
        $redis->expects(self::once())->method('sPop')->with('q')->willReturn($serialized);

        $driver = new RedisSetDriver($redis, 'q', 0, 'sched');
        $driver->setMaxProcessItems(1);

        $received = [];
        $driver->wait(function (MessageInterface $message, ?int $priority) use (&$received): void {
            $received[] = [$message->getId(), $priority];
        });

        self::assertSame([['id-1', 100]], $received);
    }

    public function testScheduledMessageClaimedByAnotherWorkerIsNotResent(): void
    {
        $serialized = new MessageSerializer()->serialize(new Message('due', [], 'id-2', null, microtime(true) - 10));

        $redis = $this->createMock(Redis::class);
        $redis->method('zRangeByScore')->willReturnOnConsecutiveCalls([$serialized], []);
        $redis->expects(self::once())->method('zRem')->willReturn(0);
        $redis->expects(self::never())->method('sAdd');
        $redis->method('sPop')->willReturnOnConsecutiveCalls(false, 'x');

        $driver = new RedisSetDriver($redis, 'q', 0, 'sched');
        $driver->setMaxProcessItems(1);
        $driver->setSerializer(new class implements \Tomaj\Hermes\SerializerInterface {
            public function serialize(MessageInterface $message): string
            {
                return 'x';
            }
            public function unserialize(string $string): MessageInterface
            {
                return new Message('fallback', [], 'fallback');
            }
        });

        $ids = [];
        $driver->wait(function (MessageInterface $message) use (&$ids): void {
            $ids[] = $message->getId();
        });
        self::assertSame(['fallback'], $ids);
    }
}
