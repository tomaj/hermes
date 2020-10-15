<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Restart\RestartException;

class RedisSetDriverTest extends TestCase
{
    public function testConstructorShouldThrowExceptionForInvalidRedis()
    {
        $this->expectException(\InvalidArgumentException::class);
        new RedisSetDriver(new \stdClass(), 'testkey');
    }

    public function testConstructorShouldWorkWithPredis()
    {
        $redis = $this->createMock(\Predis\Client::class);
        $this->assertInstanceof('Tomaj\Hermes\Driver\RedisSetDriver', new RedisSetDriver($redis, 'key'));
    }

    public function testConstructorShouldWorkWithRedis()
    {
        $redis = $this->createMock(\Redis::class);
        $this->assertInstanceof('Tomaj\Hermes\Driver\RedisSetDriver', new RedisSetDriver($redis, 'key'));
    }

    public function testPredisSendMessage()
    {
        $message = new Message('message1key', ['a' => 'b']);

        $redis = $this->getMockBuilder(\Predis\Client::class)
            ->addMethods(['sadd'])
            ->getMock();
        $redis->expects($this->once())
            ->method('sadd')
            ->with('mykey1', (new MessageSerializer)->serialize($message));
        $driver = new RedisSetDriver($redis, 'mykey1');
        $driver->send($message);
    }

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

    public function testPredisWaitForMessage()
    {
        $message = new Message('message1', ['test' => 'value']);

        $redis = $this->getMockBuilder(\Predis\Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['spop', 'zrangebyscore'])
            ->getMock();

        $redis->expects($this->once())
            ->method('zrangebyscore')
            ->will($this->returnValue([]));
        $redis->expects($this->once())
            ->method('spop')
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

    public function testRestartBeforeStart()
    {
        $redis = $this->getMockBuilder(\Redis::class)
            ->getMock();

        $processed = [];
        $driver = new RedisSetDriver($redis, 'mykey1', 0);
        $driver->setRestart(new CustomRestart((new \DateTime())->modify("+5 minutes")));

        $this->expectException(RestartException::class);

        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });
    }
}
