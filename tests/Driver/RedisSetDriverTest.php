<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\SerializerInterface;
use Tomaj\Hermes\Test\Shutdown\PollBudgetShutdown;

/**
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 * @requires extension redis
 */
class RedisSetDriverTest extends TestCase
{
    public function testSetupPriorityQueue(): void
    {
        $redis = self::createStub(Redis::class);
        $driver = new RedisSetDriver($redis, 'test_key');

        // Test that we can setup a priority queue
        self::expectNotToPerformAssertions();
        $driver->setupPriorityQueue('high_priority', 200);
    }

    public function testConstructorSetsDefaults(): void
    {
        $redis = self::createStub(Redis::class);
        $driver = new RedisSetDriver($redis);

        // Constructor should work with defaults
        self::assertSame(0, $driver->processed());
    }

    public function testConstructorWithParameters(): void
    {
        $redis = self::createStub(Redis::class);
        $driver = new RedisSetDriver($redis, 'custom_key', 5, 'custom_schedule');

        // Constructor should work with custom parameters
        self::assertSame(0, $driver->processed());
    }

    public function testTraitsAreUsed(): void
    {
        $redis = self::createStub(Redis::class);
        $driver = new RedisSetDriver($redis, 'test_key');

        // Test MaxItemsTrait methods
        self::assertSame(0, $driver->processed());
        self::assertTrue($driver->shouldProcessNext());

        // Test SerializerAwareTrait methods (we can only test setSerializer)
        $customSerializer = self::createStub(SerializerInterface::class);
        $driver->setSerializer($customSerializer);
    }

    public function testSendWithUnknownPriority(): void
    {
        $redis = self::createStub(Redis::class);
        $driver = new RedisSetDriver($redis, 'test_key');

        $message = new Message('test', ['data' => 'value']);

        $this->expectException(UnknownPriorityException::class);
        $this->expectExceptionMessageIs('Unknown priority 999');

        $driver->send($message, 999);
    }

    public function testWaitWithUnknownPriority(): void
    {
        $redis = self::createStub(Redis::class);
        // PollBudgetShutdown: if validation is skipped Infection kills the mutant via
        // ShutdownException instead of busy-waiting (maxProcessItems 0 means unlimited)
        $driver = new RedisSetDriver($redis, 'test_key', 0);
        $driver->setShutdown(new PollBudgetShutdown());

        $this->expectException(UnknownPriorityException::class);
        $this->expectExceptionMessageIs('Unknown priority 999');

        $driver->wait(static function (): void {}, [999]);
    }

    public function testSendWithValidPriority(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
              ->method('sAdd')
              ->willReturn(1);

        $driver = new RedisSetDriver($redis, 'test_key');

        $message = new Message('test', ['data' => 'value']);
        $result = $driver->send($message, 100); // 100 is the default priority that gets set up

        self::assertTrue($result);
    }
}
