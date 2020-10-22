<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Test\Driver;

use PHPUnit\Framework\TestCase;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use Tomaj\Hermes\Driver\RabbitMqDriver;
use Tomaj\Hermes\Message;

/**
 * Class RabbitMqDriverTest
 * @package Tomaj\Hermes\Test\Driver
 * @covers \Tomaj\Hermes\Driver\RabbitMqDriver
 * @covers \Tomaj\Hermes\Message
 * @covers \Tomaj\Hermes\MessageSerializer
 */
class RabbitMqDriverTest extends TestCase
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

        $channel = $this->getMockBuilder(AMQPChannel::class)
            ->disableOriginalConstructor()
            ->getMock();

        $messages = [];
        $channel->expects($this->once())
            ->method('basic_publish')
            ->will($this->returnCallback(function (AMQPMessage $msg, $exchange = "", $routing_key = "", $mandatory = false, $immediate = false, $ticket = null) use (&$messages) {
                $messages[] = array($msg, $exchange, $routing_key, $mandatory, $immediate, $ticket);
            }));

        $driver = new RabbitMqDriver($channel, 'mykey1');
        $driver->send($message);

        $this->assertCount(1, $messages);

    }
}
