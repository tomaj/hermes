<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Shutdown\RedisShutdown;

/**
 * Class RedisShutdownTest
 * @package Tomaj\Hermes\Test\Shutdown
 * @covers \Tomaj\Hermes\Shutdown\RedisShutdown
 */
class RedisShutdownTest extends TestCase
{
    public function testInitWithoutRedis()
    {
        $redis = null;
        $this->expectException(\InvalidArgumentException::class);
        new RedisShutdown($redis);
    }

    public function testShouldShutdownWithoutRedisEntry()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $redisShutdown = new RedisShutdown($redis);
        $this->assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithFutureEntry()
    {
        $futureTime = (new \DateTime())->modify('+1 month')->format('U');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($futureTime);

        $redisShutdown = new RedisShutdown($redis);
        $this->assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithEntryAfterStartTime()
    {
        $pastTime = (new \DateTime())->modify('-1 month')->format('U');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($pastTime);

        $redisShutdown = new RedisShutdown($redis);
        $this->assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownSuccess()
    {
        $startTime = (new \DateTime())->modify('-1 hour');
        $shutdownTime = (new \DateTime())->modify('-5 minutes')->format('U');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($shutdownTime);

        $redisShutdown = new RedisShutdown($redis);
        $this->assertTrue($redisShutdown->shouldShutdown($startTime));
    }

    public function testShutdownStoredCorrectValueToRedis()
    {
        $shutdownTime = (new \DateTime())->modify('-5 minutes');
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('set')
            ->with('hermes_shutdown', $shutdownTime->format('U'))
            ->willReturn(true);

        $redisShutdown = new RedisShutdown($redis);
        $this->assertTrue($redisShutdown->shutdown($shutdownTime));
    }

    public function testShutdownStoredCorrectValueToRedisPredis()
    {
        $shutdownTime = (new \DateTime())->modify('-5 minutes');
        $redis = $this->getMockBuilder(\Predis\Client::class)
            ->addMethods(['set'])
            ->getMock();
        $redis->expects($this->once())
            ->method('set')
            ->with('hermes_shutdown', $shutdownTime->format('U'))
            ->willReturn(new \Predis\Response\Status('OK'));

        $redisShutdown = new RedisShutdown($redis);
        $this->assertTrue($redisShutdown->shutdown($shutdownTime));
    }
}
