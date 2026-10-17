<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\TestCase;
use Predis\Response\Status;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Test\Double\PredisClientDouble;

/**
 * Class RedisShutdownTest
 *
 * @package Tomaj\Hermes\Test\Shutdown
 * @covers \Tomaj\Hermes\Shutdown\PredisShutdown
 */
class PredisShutdownTest extends TestCase
{
    public function testShouldShutdownWithoutPredisEntry(): void
    {
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['get'])
            ->getMock();
        $redis->expects(self::once())
            ->method('get')
            ->willReturn(null);

        $redisShutdown = new PredisShutdown($redis);
        self::assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithFutureEntry(): void
    {
        $futureTime = new \DateTime()->modify('+1 month')->format('U');
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['get'])
            ->getMock();
        $redis->expects(self::once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($futureTime);

        $redisShutdown = new PredisShutdown($redis);
        self::assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithEntryAfterStartTime(): void
    {
        $pastTime = new \DateTime()->modify('-1 month')->format('U');
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['get'])
            ->getMock();
        $redis->expects(self::once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($pastTime);

        $redisShutdown = new PredisShutdown($redis);
        self::assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownSuccess(): void
    {
        $startTime = new \DateTime()->modify('-1 hour');
        $shutdownTime = new \DateTime()->modify('-5 minutes')->format('U');
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['get'])
            ->getMock();

        $redis->expects(self::once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($shutdownTime);

        $redisShutdown = new PredisShutdown($redis);
        self::assertTrue($redisShutdown->shouldShutdown($startTime));
    }

    public function testWorkerStartedAtTheShutdownTimeKeepsRunning(): void
    {
        $startTime = new \DateTime('@1700000000');
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['get'])
            ->getMock();
        $redis->expects(self::once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn('1700000000');

        self::assertFalse(new PredisShutdown($redis)->shouldShutdown($startTime));
    }

    public function testShutdownStoredCorrectValueToRedis(): void
    {
        $shutdownTime = new \DateTime()->modify('-5 minutes');
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['set'])
            ->getMock();
        $redis->expects(self::once())
            ->method('set')
            ->with('hermes_shutdown', $shutdownTime->format('U.u'))
            ->willReturn(new Status('OK'));

        $redisShutdown = new PredisShutdown($redis);
        self::assertTrue($redisShutdown->shutdown($shutdownTime));
    }

    public function testShutdownStoredCorrectValueToRedisPredis(): void
    {
        $shutdownTime = new \DateTime()->modify('-5 minutes');
        $redis = $this->getMockBuilder(PredisClientDouble::class)
            ->onlyMethods(['set'])
            ->getMock();
        $redis->expects(self::once())
            ->method('set')
            ->with('hermes_shutdown', $shutdownTime->format('U.u'))
            ->willReturn(new Status('OK'));

        $redisShutdown = new PredisShutdown($redis);
        self::assertTrue($redisShutdown->shutdown($shutdownTime));
    }
}
