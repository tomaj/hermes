<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\Deprecation;
use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;

/**
 * @final since 5.1, final in 6.0: wrap the class or implement its interface instead of extending it
 */
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

    /** @var bool */
    private $durable;

    /**
     * @param AMQPLazyConnection $connection
     * @param string $queue
     * @param array<string, mixed> $amqpMessageProperties
     * @param int $refreshInterval
     * @param string $consumerTag
     * @param bool $durable declare a durable queue and publish persistent messages (delivery_mode 2). The default
     *                      false keeps the 5.0 behaviour; 6.0 defaults to true. Pass it explicitly so the code
     *                      means the same on 6.0.
     */
    public function __construct(AMQPLazyConnection $connection, string $queue, array $amqpMessageProperties = [], int $refreshInterval = 0, string $consumerTag = 'hermes', bool $durable = false)
    {
        Deprecation::subclass($this, self::class);
        if (func_num_args() < 6) {
            Deprecation::trigger('LazyRabbitMqDriver declares a non-durable queue; 6.0 declares a durable queue and publishes persistent messages unless you pass $durable = false. Pass $durable explicitly: false keeps the queue created by 5.x, true matches the 6.0 default.');
        }
        $this->durable = $durable;
        if ($durable && !array_key_exists('delivery_mode', $amqpMessageProperties)) {
            $amqpMessageProperties['delivery_mode'] = AMQPMessage::DELIVERY_MODE_PERSISTENT;
        }
        $this->connection = $connection;
        $this->queue = $queue;
        $this->amqpMessageProperties = $amqpMessageProperties;
        $this->refreshInterval = $refreshInterval;
        $this->consumerTag = $consumerTag;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException closed or blocked channel or connection
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
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
     * @throws \PhpAmqpLib\Exception\AMQPOutOfBoundsException
     * @throws \PhpAmqpLib\Exception\AMQPRuntimeException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @throws \ErrorException
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

    /**
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     * @return AMQPChannel
     */
    private function getChannel(): AMQPChannel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($this->queue, false, $this->durable, false, false);
        return $this->channel;
    }
}
