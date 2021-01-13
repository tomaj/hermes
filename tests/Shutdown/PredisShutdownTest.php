<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Shutdown;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Predis\Response\Status;
use Tomaj\Hermes\Shutdown\PredisShutdown;

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
        $redis = $this->getMockBuilder(Client::class)
            ->addMethods(['get'])
            ->getMock();
        $redis->expects($this->once())
            ->method('get')
            ->willReturn(null);

        $redisShutdown = new PredisShutdown($redis);
        $this->assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithFutureEntry(): void
    {
        $futureTime = (new \DateTime())->modify('+1 month')->format('U');
        $redis = $this->getMockBuilder(Client::class)
            ->addMethods(['get'])
            ->getMock();
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($futureTime);

        $redisShutdown = new PredisShutdown($redis);
        $this->assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownWithEntryAfterStartTime(): void
    {
        $pastTime = (new \DateTime())->modify('-1 month')->format('U');
        $redis = $this->getMockBuilder(Client::class)
            ->addMethods(['get'])
            ->getMock();
        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($pastTime);

        $redisShutdown = new PredisShutdown($redis);
        $this->assertFalse($redisShutdown->shouldShutdown(new \DateTime()));
    }

    public function testShouldShutdownSuccess(): void
    {
        $startTime = (new \DateTime())->modify('-1 hour');
        $shutdownTime = (new \DateTime())->modify('-5 minutes')->format('U');
        $redis = $this->getMockBuilder(Client::class)
            ->addMethods(['get'])
            ->getMock();

        $redis->expects($this->once())
            ->method('get')
            ->with('hermes_shutdown')
            ->willReturn($shutdownTime);

        $redisShutdown = new PredisShutdown($redis);
        $this->assertTrue($redisShutdown->shouldShutdown($startTime));
    }

    public function testShutdownStoredCorrectValueToRedis(): void
    {
        $shutdownTime = (new \DateTime())->modify('-5 minutes');
        $redis = $this->getMockBuilder(Client::class)
            ->addMethods(['set'])
            ->getMock();
        $redis->expects($this->once())
            ->method('set')
            ->with('hermes_shutdown', $shutdownTime->format('U'))
            ->willReturn(new Status('OK'));

        $redisShutdown = new PredisShutdown($redis);
        $this->assertTrue($redisShutdown->shutdown($shutdownTime));
    }

    public function testShutdownStoredCorrectValueToRedisPredis(): void
    {
        $shutdownTime = (new \DateTime())->modify('-5 minutes');
        $redis = $this->getMockBuilder(\Predis\Client::class)
            ->addMethods(['set'])
            ->getMock();
        $redis->expects($this->once())
            ->method('set')
            ->with('hermes_shutdown', $shutdownTime->format('U'))
            ->willReturn(new Status('OK'));

        $redisShutdown = new PredisShutdown($redis);
        $this->assertTrue($redisShutdown->shutdown($shutdownTime));
    }
}
