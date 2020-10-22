<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PhpAmqpLib\Connection\AMQPLazyConnection;
use PHPUnit\Framework\TestCase;
use PhpAmqpLib\Channel\AMQPChannel;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Message;

/**
 * Class LazyRabbitMqDriverTest
 * @package Tomaj\Hermes\Test\Driver
 * @covers \Tomaj\Hermes\Driver\LazyRabbitMqDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class LazyRabbitMqDriverTest extends TestCase
{
    public function testDriverPublishToChannel()
    {
        if (!class_exists('PhpAmqpLib\Connection\AMQPConnection')) {
            $this->markTestSkipped("amqp-php not installed");
        }
        if (!class_exists('PhpAmqpLib\Channel\AMQPChannel')) {
            $this->markTestSkipped("Please update AMQP to version >= 1.0");
        }

        $message = new Message('message1key', ['a' => 'b']);

        $connection = $this->getMockBuilder(AMQPLazyConnection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $channel = $this->getMockBuilder(AMQPChannel::class)
            ->disableOriginalConstructor()
            ->getMock();
        $channel->expects($this->once())
            ->method('queue_declare');
        $channel->expects($this->once())
            ->method('basic_publish');

        $connection->expects($this->once())
            ->method('channel')
            ->will($this->returnValue($channel));

        $driver = new LazyRabbitMqDriver($connection, 'mykey1');
        $driver->send($message);
    }
}
