<?php

namespace Tomaj\Hermes\Driver;

use Closure;
use Exception;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMqDriver implements DriverInterface
{
    private $host;

    private $port;

    private $user;

    private $password;

    private $vhost;

    private $queue;
    
    public function __construct($host = 'localhost', $port = 5672, $user = 'guest', $password = 'guest', $vhost = '/', $queue = 'hermes')
    {
        if (!class_exists('PhpAmqpLib\Connection\AMQPStreamConnection')) {
            throw new Exception('You need to install "videlalvaro/php-amqplib" composer package for rabbitmq driver');
        }

        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->vhost = $vhost;
        $this->queue = $queue;
    

        $this->serializer = new MessageSerializer();
    }

    private function getRabbitmqChannel()
    {
        $connection = new AMQPStreamConnection($this->host, $this->port, $this->user, $this->password);
        $channel = $connection->channel();
        $channel->queue_declare($this->queue, false, false, false, false);
        return $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message)
    {
        $rabbitChannel = $this->getRabbitmqChannel();
        $rabbitMessage = new AMQPMessage($this->serializer->serialize($message));
        $rabbitChannel->basic_publish($rabbitMessage, '', $this->queue);
        $rabbitChannel->close();
    }

    /**
     * {@inheritdoc}
     */
    public function wait(Closure $callback)
    {
        $rabbitChannel = $this->getRabbitmqChannel();
        $rabbitChannel->basic_consume($this->queue, '', false, true, false, false, function ($rabbitMessage) use ($callback) {
            $message = $this->serializer->unserialize($rabbitMessage->body);
            $callback($message);
        });
    }
}
