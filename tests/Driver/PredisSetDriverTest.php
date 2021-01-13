<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;

/**
 * Class RedisSetDriverTest
 *
 * @package Tomaj\Hermes\Test\Driver
 * @covers \Tomaj\Hermes\Driver\PredisSetDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class PredisSetDriverTest extends TestCase
{
    public function testPredisSendMessage(): void
    {
        $message = new Message('message1key', ['a' => 'b']);

        $redis = $this->getMockBuilder(\Predis\Client::class)
            ->addMethods(['sadd'])
            ->getMock();
        $redis->expects($this->once())
            ->method('sadd')
            ->with('mykey1', [(new MessageSerializer)->serialize($message)]);
        $driver = new PredisSetDriver($redis, 'mykey1');
        $driver->send($message);
    }

    public function testPredisWaitForMessage(): void
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
        $driver = new PredisSetDriver($redis, 'mykey1', 0);
        $driver->setMaxProcessItems(1);
        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });

        $this->assertCount(1, $processed);
        $this->assertEquals($message->getId(), $processed[0]->getId());
    }

    public function testRestartBeforeStart(): void
    {
        $redis = $this->getMockBuilder(\Predis\Client::class)
            ->getMock();

        $processed = [];
        $driver = new PredisSetDriver($redis, 'mykey1', 0);
        $driver->setShutdown(new CustomShutdown((new \DateTime())->modify("+5 minutes")));

        $this->expectException(ShutdownException::class);

        $driver->wait(function ($message) use (&$processed) {
            $processed[] = $message;
        });
    }

    public function testPublishToUnknownQueue(): void
    {
        $redis = $this->getMockBuilder(\Predis\Client::class)
            ->getMock();

        $driver = new PredisSetDriver($redis);

        $this->expectException(UnknownPriorityException::class);
        $driver->send(new Message('test', ['a' => 'b']), 1000);
    }
}
