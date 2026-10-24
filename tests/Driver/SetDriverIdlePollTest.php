<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Test\Double\PredisClientDouble;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

/**
 * How the Redis and Predis set drivers poll an empty queue and pick a priority queue.
 *
 * @covers \Tomaj\Hermes\Driver\PredisSetDriver
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 */
class SetDriverIdlePollTest extends TestCase
{
    private int $polls = 0;

    /** @var list<string> */
    private array $popped = [];

    /**
     * @param array<string, string> $queues key => message in that set
     */
    private function predis(int $refreshInterval, array $queues = []): PredisSetDriver
    {
        $redis = self::createStub(PredisClientDouble::class);
        $redis->method('zrangebyscore')->willReturnCallback(function (): array {
            $this->polls++;
            return [];
        });
        $redis->method('spop')->willReturnCallback(function (string $key) use (&$queues): ?string {
            $this->popped[] = $key;
            $message = $queues[$key] ?? null;
            unset($queues[$key]);
            return $message;
        });
        return new PredisSetDriver($redis, 'hermes', $refreshInterval);
    }

    /**
     * @param array<string, string> $queues key => message in that set
     */
    private function redis(int $refreshInterval, array $queues = []): RedisSetDriver
    {
        $redis = self::createStub(Redis::class);
        $redis->method('zRangeByScore')->willReturnCallback(function (): array {
            $this->polls++;
            return [];
        });
        $redis->method('sPop')->willReturnCallback(function (string $key) use (&$queues): string|false {
            $this->popped[] = $key;
            $message = $queues[$key] ?? false;
            unset($queues[$key]);
            return $message;
        });
        return new RedisSetDriver($redis, 'hermes', $refreshInterval);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function drivers(): array
    {
        return ['predis' => ['predis'], 'redis' => ['redis']];
    }

    /**
     * @param array<string, string> $queues
     */
    private function driver(string $kind, int $refreshInterval, array $queues = []): PredisSetDriver|RedisSetDriver
    {
        if ($kind === 'redis' && !extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not installed');
        }
        return $kind === 'predis' ? $this->predis($refreshInterval, $queues) : $this->redis($refreshInterval, $queues);
    }

    private function waitUntilShutdown(PredisSetDriver|RedisSetDriver $driver): float
    {
        $start = microtime(true);
        try {
            $driver->wait(static function (): void {
                self::fail('the queue is empty');
            });
            self::fail('wait() must end with ShutdownException');
        } catch (ShutdownException) {
        }
        return microtime(true) - $start;
    }

    #[DataProvider('drivers')]
    public function testWithoutRefreshIntervalEveryEmptyPollChecksShutdownOnce(string $kind): void
    {
        $driver = $this->driver($kind, 0);
        $driver->setShutdown(new PollBudgetShutdown(3));

        $this->waitUntilShutdown($driver);

        // three checks allowed: one per poll, the fourth one stops the worker
        self::assertSame(3, $this->polls);
    }

    #[DataProvider('drivers')]
    public function testAnEmptyPollSleepsForTheRefreshInterval(string $kind): void
    {
        $driver = $this->driver($kind, 1);
        $driver->setShutdown(new PollBudgetShutdown(2));

        $elapsed = $this->waitUntilShutdown($driver);

        self::assertSame(1, $this->polls);
        self::assertGreaterThanOrEqual(0.9, $elapsed);
    }

    #[DataProvider('drivers')]
    public function testShutdownIsCheckedBeforeTheIdleSleep(string $kind): void
    {
        $driver = $this->driver($kind, 5);
        // the check at the top of the loop passes, the one before the sleep stops the worker
        $driver->setShutdown(new PollBudgetShutdown(1));

        $elapsed = $this->waitUntilShutdown($driver);

        self::assertSame(1, $this->polls);
        self::assertLessThan(1.0, $elapsed);
    }

    #[DataProvider('drivers')]
    public function testOnlyTheFirstNonEmptyPriorityQueueIsPopped(string $kind): void
    {
        $serializer = new MessageSerializer();
        $high = $serializer->serialize(new Message('type', [], 'high'));
        $low = $serializer->serialize(new Message('type', [], 'low'));
        $driver = $this->driver($kind, 0, ['hermes_200' => $high, 'hermes_50' => $low]);
        $driver->setupPriorityQueue('hermes_200', 200);
        $driver->setupPriorityQueue('hermes_50', 50);
        $driver->setMaxProcessItems(1);

        $received = [];
        $driver->wait(static function (Message $message, int $priority) use (&$received): void {
            $received[] = [$message->getId(), $priority];
        });

        self::assertSame([['high', 200]], $received);
        // the low queue keeps its message for the next round
        self::assertSame(['hermes_200'], $this->popped);
    }
}
