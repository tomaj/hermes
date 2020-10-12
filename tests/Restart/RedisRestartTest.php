<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Test;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Restart\RedisRestart;

class RedisRestartTest extends PHPUnit_Framework_TestCase
{

    public function testInitWithoutRedis()
    {
        $redis = null;
        $this->setExpectedException('InvalidArgumentException', 'Predis\Client or Redis instance required');
        new RedisRestart($redis);
    }

    public function testShouldRestartWithoutRedisEntry()
    {
        $redis = $this->getMock('\Redis', ['get']);
        $redis->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $redisRestart = new RedisRestart($redis);
        $this->assertFalse($redisRestart->shouldRestart(new \DateTime()));
    }

    public function testShouldRestartWithFutureEntry()
    {
        $futureTime = (new \DateTime())->modify('+1 month')->format('U');
        $redis = $this->getMock('\Redis', ['get']);
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_restart')
            ->willReturn($futureTime);

        $redisRestart = new RedisRestart($redis);
        $this->assertFalse($redisRestart->shouldRestart(new \DateTime()));
    }

    public function testShouldRestartWithEntryAfterStartTime()
    {
        $pastTime = (new \DateTime())->modify('-1 month')->format('U');
        $redis = $this->getMock('\Redis', ['get']);
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_restart')
            ->willReturn($pastTime);

        $redisRestart = new RedisRestart($redis);
        $this->assertFalse($redisRestart->shouldRestart(new \DateTime()));
    }

    public function testShouldRestartSuccess()
    {
        $startTime = (new \DateTime())->modify('-1 hour');
        $restartTime = (new \DateTime())->modify('-5 minutes')->format('U');
        $redis = $this->getMock('\Redis', ['get']);
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_restart')
            ->willReturn($restartTime);

        $redisRestart = new RedisRestart($redis);
        $this->assertTrue($redisRestart->shouldRestart($startTime));
    }

    public function testRestartStoredCorrectValueToRedis()
    {
        $restartTime = (new \DateTime())->modify('-5 minutes');
        $redis = $this->getMock('\Redis', ['set', 'get']);
        $redis->expects($this->once())
            ->method('set')
            ->with('hermes_restart', $restartTime->format('U'))
            ->willReturn(true);

        $redisRestart = new RedisRestart($redis);
        $this->assertTrue($redisRestart->restart($restartTime));
    }
}
