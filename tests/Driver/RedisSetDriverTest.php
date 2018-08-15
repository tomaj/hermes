<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Test\Handler\TestHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;

class RedisSetDriverTest extends PHPUnit_Framework_TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructorShouldThrowExceptionForInvalidRedis()
    {
        new RedisSetDriver(new \stdClass(), 'testkey');
    }

    public function testConstructorShouldWorkWithPredis()
    {
        $redis = $this->getMock('Predis\Client');
        $this->assertInstanceof('Tomaj\Hermes\Driver\RedisSetDriver', new RedisSetDriver($redis, 'key'));
    }

    public function testConstructorShouldWorkWithRedis()
    {
        $redis = $this->getMock('Redis');
        $this->assertInstanceof('Tomaj\Hermes\Driver\RedisSetDriver', new RedisSetDriver($redis, 'key'));
    }

    public function testPredisSendMessage()
    {
        $message = new Message('message1key', ['a' => 'b']);

        $redis = $this->getMock('Predis\Client', ['sadd']);
        $redis->expects($this->once())
            ->method('sadd')
            ->with('mykey1', (new MessageSerializer)->serialize($message));
        $driver = new RedisSetDriver($redis, 'mykey1');
        $driver->send($message);
    }

    public function testRedisSendMessage()
    {
        $message = new Message('message2key', ['c' => 'd']);

        $redis = $this->getMock('Redis', ['sadd']);
        $redis->expects($this->once())
            ->method('sadd')
            ->with('mykey2', (new MessageSerializer)->serialize($message));
        $driver = new RedisSetDriver($redis, 'mykey2');
        $driver->send($message);
    }

    public function testPredisWaitForMessage()
    {
        $message = new Message('message1', ['test' => 'value']);

        $redis = $this->getMock('Predis\Client', ['sPop']);
        $redis->expects($this->at(0))
            ->method('zrangebyscore')
            ->will($this->returnValue([]));
        $redis->expects($this->at(1))
            ->method('sPop')
            ->with('mykey1')
            ->will($this->returnValue((new MessageSerializer)->serialize($message)));

        $processed = [];
        $driver = new RedisSetDriver($redis, 'mykey1', 0);
        $driver->setMaxProcessItems(1);
        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });

        $this->assertEquals(1, count($processed));
        $this->assertEquals($message->getId(), $processed[0]->getId());
    }

    public function testRedisWaitForMessage()
    {
        $message = new Message('message1', ['test' => 'value']);

        $redis = $this->getMock('Redis', ['zRangeByScore', 'sPop']);
        $redis->expects($this->at(0))
            ->method('zRangeByScore')
            ->will($this->returnValue([]));
        $redis->expects($this->at(1))
            ->method('sPop')
            ->with('mykey1')
            ->will($this->returnValue((new MessageSerializer)->serialize($message)));

        $processed = [];
        $driver = new RedisSetDriver($redis, 'mykey1', 0);
        $driver->setMaxProcessItems(1);
        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });

        $this->assertEquals(1, count($processed));
        $this->assertEquals($message->getId(), $processed[0]->getId());
    }
}
