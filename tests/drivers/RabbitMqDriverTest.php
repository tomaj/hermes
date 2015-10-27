<?php

namespace Tomaj\Hermes;

use PHPUnit_Framework_TestCase;
use Tomaj\Hermes\Handler\TestHandler;
use Tomaj\Hermes\Driver\RabbitMqDriver;
use PhpAmqpLib\Message\AMQPMessage;

require __DIR__ . '/../../vendor/autoload.php';

class RabbitMqDriverTest extends PHPUnit_Framework_TestCase
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

        $channel = $this->getMock('PhpAmqpLib\Channel\AMQPChannel', ['basic_publish'], [], '', false);

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
