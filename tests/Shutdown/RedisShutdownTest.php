<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\TestCase;
use Redis;
use Tomaj\Hermes\Shutdown\RedisShutdown;

/**
 * Class RedisShutdownTest
 *
 * @package Tomaj\Hermes\Test\Shutdown
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 * @requires extension redis
 */
class RedisShutdownTest extends TestCase
{
    public function testShouldShutdownWithoutRedisEntry(): void
    {
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('get')
            ->willReturn(null);

        $redisShutdown = new RedisShutdown($redis);
        self::assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithFutureEntry(): void
    {
        $futureTime = new \DateTime()->modify('+1 month')->format('U');
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($futureTime);

        $redisShutdown = new RedisShutdown($redis);
        self::assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithEntryAfterStartTime(): void
    {
        $pastTime = new \DateTime()->modify('-1 month')->format('U');
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($pastTime);

        $redisShutdown = new RedisShutdown($redis);
        self::assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownSuccess(): void
    {
        $startTime = new \DateTime()->modify('-1 hour');
        $shutdownTime = new \DateTime()->modify('-5 minutes')->format('U');
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($shutdownTime);

        $redisShutdown = new RedisShutdown($redis);
        self::assertTrue($redisShutdown->shouldShutdown($startTime));
    }

    public function testShutdownStoredCorrectValueToRedis(): void
    {
        $shutdownTime = new \DateTime()->modify('-5 minutes');
        $redis = $this->createMock(Redis::class);
        $redis->expects(self::once())
            ->method('set')
            ->with('hermes_shutdown', $shutdownTime->format('U'))
            ->willReturn(true);

        $redisShutdown = new RedisShutdown($redis);
        self::assertTrue($redisShutdown->shutdown($shutdownTime));
    }
}
