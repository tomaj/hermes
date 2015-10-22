<?php

namespace Tomaj\Hermes\Driver;

use Closure;
use Exception;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageSerializer;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

class RabbitMqDriver implements DriverInterface
{
    private $channel;

    private $queue;
    
    public function __construct(AMQPChannel $channel, $queue)
    {
        $this->channel = $channel;
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
        $rabbitMessage = new AMQPMessage($this->serializer->serialize($message));
        $this->channel->basic_publish($rabbitMessage, '', $this->queue);
    }

    /**
     * {@inheritdoc}
     */
    public function wait(Closure $callback)
    {
        $this->channel->basic_consume($this->queue, '', false, true, false, false, function ($rabbitMessage) use ($callback) {
            $message = $this->serializer->unserialize($rabbitMessage->body);
            $callback($message);
        });

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }
}
