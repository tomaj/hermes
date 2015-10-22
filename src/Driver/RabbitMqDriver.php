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
    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var string
     */
    private $queue;

    /**
     * @var Tomaj\Hermes\SerializerInterface
     */
    private $serializer;
    
    /**
     * Create new RabbitMqDriver with provided channel.
     *
     * You have to create connection to rabbit, and setup queue outside of this class.
     * Handling connection to rabbit is up to you and you have to manage it.
     *
     * @see examples/rabbitmq folder
     *
     * @param AMQPChannel   $channel 
     * @param string        $queue  
     */
    public function __construct(AMQPChannel $channel, $queue)
    {
        $this->channel = $channel;
        $this->queue = $queue;
        $this->serializer = new MessageSerializer();
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
