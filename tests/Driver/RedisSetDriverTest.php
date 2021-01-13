<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;

/**
 * Class RedisSetDriverTest
 *
 * @package Tomaj\Hermes\Test\Driver
 * @covers \Tomaj\Hermes\Driver\RedisSetDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class RedisSetDriverTest extends TestCase
{
    public function testRedisSendMessage()
    {
        $message = new Message('message2key', ['c' => 'd']);

        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('sadd')
            ->with('mykey2', (new MessageSerializer)->serialize($message));
        $driver = new RedisSetDriver($redis, 'mykey2');
        $driver->send($message);
    }

    public function testRedisWaitForMessage()
    {
        $message = new Message('message1', ['test' => 'value']);

        $redis = $this->getMockBuilder(\Redis::class)
            ->getMock();
        $redis->expects($this->once())
            ->method('zRangeByScore')
            ->will($this->returnValue([]));
        $redis->expects($this->once())
            ->method('sPop')
            ->with('mykey1')
            ->will($this->returnValue((new MessageSerializer)->serialize($message)));

        $processed = [];
        $driver = new RedisSetDriver($redis, 'mykey1', 0);
        $driver->setMaxProcessItems(1);
        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });

        $this->assertCount(1, $processed);
        $this->assertEquals($message->getId(), $processed[0]->getId());
    }

    public function testShutdownBeforeStart()
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->getMock();

        $processed = [];
        $driver = new RedisSetDriver($redis, 'mykey1', 0);
        $driver->setShutdown(new CustomShutdown((new \DateTime())->modify("+5 minutes")));

        $this->expectException(ShutdownException::class);

        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });
    }

    public function testPublishToUnknownQueue()
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->getMock();

        $driver = new RedisSetDriver($redis);

        $this->expectException(UnknownPriorityException::class);
        $driver->send(new Message('test', ['a' => 'b']), 1000);
    }
}
