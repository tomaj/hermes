<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use Exception;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

/**
 * Class RabbitMqDriver
 * @package Tomaj\Hermes\Driver
 *
 * @deprecated use LazyRabbitMqDriver instead
 */
class RabbitMqDriver implements DriverInterface
{
    use SerializerAwareTrait;

    /**
     * @var AMQPChannel
     */
    private $channel;

    /**
     * @var string
     */
    private $queue;

    /**
     * @var array
     */
    private $amqpMessageProperties = [];

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
     * @param array         $amqpMessageProperties
     */
    public function __construct(AMQPChannel $channel, string $queue, array $amqpMessageProperties = [])
    {
        $this->channel = $channel;
        $this->queue = $queue;
        $this->amqpMessageProperties = $amqpMessageProperties;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message): bool
    {
        $rabbitMessage = new AMQPMessage($this->serializer->serialize($message), $this->amqpMessageProperties);
        $this->channel->basic_publish($rabbitMessage, '', $this->queue);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function wait(Closure $callback): void
    {
        $this->channel->basic_consume(
            $this->queue,
            '',
            false,
            false,
            false,
            false,
            function ($rabbitMessage) use ($callback) {
                $message = $this->serializer->unserialize($rabbitMessage->body);
                $callback($message);
                $rabbitMessage->delivery_info['channel']->basic_ack($rabbitMessage->delivery_info['delivery_tag']);
            }
        );

        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }
    }
}
