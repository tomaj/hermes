<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;

class LazyRabbitMqDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    /** @var AMQPLazyConnection */
    private $connection;
    
    /** @var AMQPChannel */
    private $channel;

    /** @var string */
    private $queue;

    /** @var array<string, mixed> */
    private $amqpMessageProperties = [];

    /** @var integer */
    private $refreshInterval;

    /** @var string */
    private $consumerTag;

    /**
     * @param AMQPLazyConnection $connection
     * @param string $queue
     * @param array<string, mixed> $amqpMessageProperties
     * @param int $refreshInterval
     * @param string $consumerTag
     */
    public function __construct(AMQPLazyConnection $connection, string $queue, array $amqpMessageProperties = [], int $refreshInterval = 0, string $consumerTag = 'hermes')
    {
        $this->connection = $connection;
        $this->queue = $queue;
        $this->amqpMessageProperties = $amqpMessageProperties;
        $this->refreshInterval = $refreshInterval;
        $this->consumerTag = $consumerTag;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message, int $priority = Dispatcher::PRIORITY_MEDIUM): bool
    {
        $rabbitMessage = new AMQPMessage($this->serializer->serialize($message), $this->amqpMessageProperties);
        $this->getChannel()->basic_publish($rabbitMessage, '', $this->queue);
        return true;
    }

    /**
     * @param string $name
     * @param int $priority
     *
     * @throws NotSupportedException
     */
    public function setupPriorityQueue(string $name, int $priority): void
    {
        throw new NotSupportedException("LazyRabbitMqDriver is not supporting priority queues now");
    }

    /**
     * {@inheritdoc}
     *
     * @throws ShutdownException
     */
    public function wait(Closure $callback, array $priorities = []): void
    {
        while (true) {
            $this->getChannel()->basic_consume(
                $this->queue,
                $this->consumerTag,
                false,
                true,
                false,
                false,
                function ($rabbitMessage) use ($callback) {
                    $message = $this->serializer->unserialize($rabbitMessage->body);
                    $callback($message);
                }
            );

            while (count($this->getChannel()->callbacks)) {
                $this->getChannel()->wait(null, true);
                $this->checkShutdown();
                if (!$this->shouldProcessNext()) {
                    break 2;
                }
                if ($this->refreshInterval) {
                    sleep($this->refreshInterval);
                }
            }
        }

        $this->getChannel()->close();
        $this->connection->close();
    }
    
    private function getChannel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($this->queue, false, false, false, false);
        return $this->channel;
    }
}
