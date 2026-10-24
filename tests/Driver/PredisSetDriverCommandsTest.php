<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Test\Double\PredisClientDouble;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

/**
 * The exact Redis commands PredisSetDriver sends.
 *
 * @covers \Tomaj\Hermes\Driver\PredisSetDriver
 */
class PredisSetDriverCommandsTest extends TestCase
{
    /**
     * @return PredisClientDouble&MockObject
     */
    private function redis(): PredisClientDouble
    {
        return $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['sadd', 'zadd', 'zrem', 'zrangebyscore', 'spop'])
            ->getMock();
    }

    public function testMessageWithoutExecuteAtGoesToTheQueue(): void
    {
        $message = new Message('type', [], 'id-1');
        $redis = $this->redis();
        $redis->expects(self::once())->method('sadd')
            ->with('hermes', [new MessageSerializer()->serialize($message)]);
        $redis->expects(self::never())->method('zadd');

        self::assertTrue(new PredisSetDriver($redis)->send($message));
    }

    public function testMessageWithFutureExecuteAtIsScheduled(): void
    {
        $executeAt = microtime(true) + 3600;
        $message = new Message('type', [], 'id-1', null, $executeAt);
        $redis = $this->redis();
        $redis->expects(self::once())->method('zadd')
            ->with('hermes_schedule', [new MessageSerializer()->serialize($message) => $executeAt]);
        $redis->expects(self::never())->method('sadd');

        self::assertTrue(new PredisSetDriver($redis)->send($message));
    }

    public function testWaitMovesOneDueScheduledMessageToTheQueue(): void
    {
        $due = new MessageSerializer()->serialize(new Message('type', [], 'due', null, microtime(true) - 60));
        $redis = $this->redis();
        $redis->expects(self::once())->method('zrangebyscore')
            ->with(
                'hermes_schedule',
                '-inf',
                self::matchesRegularExpression('/^\d+\.\d{6}$/'),
                ['LIMIT' => ['OFFSET' => 0, 'COUNT' => 1]],
            )
            ->willReturn([$due]);
        $redis->expects(self::once())->method('zrem')->with('hermes_schedule', $due)->willReturn(1);
        $redis->expects(self::once())->method('sadd')->with('hermes', [$due]);
        $redis->expects(self::once())->method('spop')->with('hermes')->willReturn($due);

        $driver = new PredisSetDriver($redis, 'hermes', 0);
        $driver->setMaxProcessItems(1);

        $received = [];
        $driver->wait(static function (MessageInterface $message) use (&$received): void {
            $received[] = $message->getId();
        });

        self::assertSame(['due'], $received);
    }

    public function testWaitSkipsADueMessageAnotherWorkerAlreadyRemoved(): void
    {
        $due = new MessageSerializer()->serialize(new Message('type', [], 'due', null, microtime(true) - 60));
        $redis = $this->redis();
        $redis->expects(self::once())->method('zrangebyscore')->willReturn([$due]);
        $redis->expects(self::once())->method('zrem')->with('hermes_schedule', $due)->willReturn(0);
        $redis->expects(self::never())->method('sadd');
        $redis->expects(self::once())->method('spop')->with('hermes')->willReturn(null);

        $driver = new PredisSetDriver($redis, 'hermes', 0);
        $driver->setShutdown(new PollBudgetShutdown(1));

        $received = [];
        try {
            $driver->wait(static function (MessageInterface $message) use (&$received): void {
                $received[] = $message->getId();
            });
            self::fail('The shutdown should stop the worker');
        } catch (ShutdownException) {
        }

        self::assertSame([], $received);
    }
}
