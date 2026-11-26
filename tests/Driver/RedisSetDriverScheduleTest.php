<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializerInterface;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

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
        $driver->setSerializer(new class implements SerializerInterface {
            #[\Override]
            public function serialize(MessageInterface $message): string
            {
                return 'x';
            }
            #[\Override]
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

    public function testDelayedMessageWithAPriorityIsScheduledInTheSetOfThatPriority(): void
    {
        $executeAt = microtime(true) + 3600;
        $message = new Message('type', [], 'id-1', null, $executeAt);
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())->method('zAdd')
            ->with('sched:200', $executeAt, new MessageSerializer()->serialize($message));

        $driver = new RedisSetDriver($redis, 'q', 0, 'sched');
        $driver->setupPriorityQueue('q_high', 200);

        self::assertTrue($driver->send($message, 200));
    }

    public function testDelayedMessageWithAnUnknownPriorityIsRejected(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::never())->method('zAdd');

        $this->expectException(UnknownPriorityException::class);
        new RedisSetDriver($redis, 'q', 0, 'sched')->send(new Message('type', [], 'id-1', null, microtime(true) + 3600), 200);
    }

    public function testDueEntriesArePromotedToTheQueueOfTheirPriority(): void
    {
        $high = new MessageSerializer()->serialize(new Message('type', [], 'high', null, microtime(true) - 60));
        // an entry without a priority, as 5.x wrote them, comes back at the default priority
        $old = new MessageSerializer()->serialize(new Message('type', [], 'old', null, microtime(true) - 60));
        $schedules = ['sched:200' => [$high], 'sched' => [$old]];
        /** @var array<string, list<string>> $sets */
        $sets = [];
        $redis = $this->createMock(Redis::class);
        $redis->method('zRangeByScore')->willReturnCallback(static function (string $key) use (&$schedules): array {
            $due = $schedules[$key] ?? [];
            unset($schedules[$key]);
            return $due;
        });
        $redis->expects(self::exactly(2))->method('zRem')->willReturn(1);
        $redis->method('sAdd')->willReturnCallback(static function (string $key, string $member) use (&$sets): int {
            $sets[$key][] = $member;
            return 1;
        });
        $redis->method('sPop')->willReturnCallback(static function (string $key) use (&$sets): string|false {
            return isset($sets[$key]) && $sets[$key] !== [] ? array_shift($sets[$key]) : false;
        });

        $driver = new RedisSetDriver($redis, 'q', 0, 'sched');
        $driver->setupPriorityQueue('q_high', 200);
        $driver->setMaxProcessItems(2);
        // a regression ends in ShutdownException instead of polling forever
        $driver->setShutdown(new PollBudgetShutdown());

        $received = [];
        $driver->wait(static function (MessageInterface $message, int $priority) use (&$received): void {
            $received[] = [$message->getId(), $priority];
        });

        self::assertSame([['high', 200], ['old', 100]], $received);
    }
}
