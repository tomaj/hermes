<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Test\Double\PredisClientDouble;

/**
 * Issue #50: queues must be polled from the highest priority number down,
 * not in reverse setup order.
 */
#[CoversClass(RedisSetDriver::class)]
#[CoversClass(PredisSetDriver::class)]
#[AllowMockObjectsWithoutExpectations]
class RedisSetDriverPriorityTest extends TestCase
{
    /** @var list<string> */
    private array $polled = [];

    private function setupPriorities(RedisSetDriver|PredisSetDriver $driver): void
    {
        $driver->setupPriorityQueue('higher', 200);
        $driver->setupPriorityQueue('top', 1000);
        $driver->setupPriorityQueue('lower', 50);
    }

    private function pop(string $key): string|false
    {
        $this->polled[] = $key;
        return $key === 'lower' ? new MessageSerializer()->serialize(new Message('t', [], 'm')) : false;
    }

    private function popOrNull(string $key): ?string
    {
        $popped = $this->pop($key);
        return $popped === false ? null : $popped;
    }

    #[RequiresPhpExtension('redis')]
    public function testRedisSetDriverPollsHighestPriorityFirst(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->method('zRangeByScore')->willReturn([]);
        $redis->method('sPop')->willReturnCallback(fn(string $key): string|false => $this->pop($key));

        $driver = new RedisSetDriver($redis, 'default', 0);
        $this->setupPriorities($driver);

        self::assertSame(50, $this->waitOnce($driver));
        self::assertSame(['top', 'higher', 'default', 'lower'], $this->polled);
    }

    public function testPredisSetDriverPollsHighestPriorityFirst(): void
    {
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['zrangebyscore', 'spop'])
            ->getMock();
        $redis->method('zrangebyscore')->willReturn([]);
        $redis->method('spop')->willReturnCallback(fn(string $key): ?string => $this->popOrNull($key));

        $driver = new PredisSetDriver($redis, 'default', 0);
        $this->setupPriorities($driver);

        self::assertSame(50, $this->waitOnce($driver));
        self::assertSame(['top', 'higher', 'default', 'lower'], $this->polled);
    }

    private function waitOnce(RedisSetDriver|PredisSetDriver $driver): ?int
    {
        $found = null;
        $driver->setMaxProcessItems(1);
        $driver->wait(static function (MessageInterface $message, int $priority) use (&$found): void {
            $found = $priority;
        });
        return $found;
    }
}
