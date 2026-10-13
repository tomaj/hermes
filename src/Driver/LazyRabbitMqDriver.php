<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
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

    /** @var bool */
    private $durable;

    /**
     * @param AMQPLazyConnection $connection
     * @param string $queue
     * @param array<string, mixed> $amqpMessageProperties
     * @param int $refreshInterval
     * @param string $consumerTag
     * @param bool $durable Declare a durable queue and publish persistent messages (delivery_mode 2).
     *                      Pass false to keep using a queue that was declared non-durable by Hermes 5.x.
     */
    public function __construct(AMQPLazyConnection $connection, string $queue, array $amqpMessageProperties = [], int $refreshInterval = 0, string $consumerTag = 'hermes', bool $durable = true)
    {
        $this->connection = $connection;
        $this->queue = $queue;
        $this->amqpMessageProperties = $amqpMessageProperties;
        $this->refreshInterval = $refreshInterval;
        $this->consumerTag = $consumerTag;
        $this->durable = $durable;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \PhpAmqpLib\Exception\AMQPChannelClosedException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionBlockedException
     * @throws \PhpAmqpLib\Exception\AMQPConnectionClosedException
     * @throws \PhpAmqpLib\Exception\AMQPTimeoutException
     */
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $properties = $this->amqpMessageProperties;
        if ($this->durable && !array_key_exists('delivery_mode', $properties)) {
            $properties['delivery_mode'] = AMQPMessage::DELIVERY_MODE_PERSISTENT;
        }
        $rabbitMessage = new AMQPMessage($this->serializer->serialize($message), $properties);
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
        throw new NotSupportedException('LazyRabbitMqDriver is not supporting priority queues now');
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
        // Deliver one unacknowledged message at a time to this worker.
        $this->getChannel()->basic_qos(0, 1, false);
        while (true) {
            $this->getChannel()->basic_consume(
                $this->queue,
                $this->consumerTag,
                false,
                false,
                false,
                false,
                function (AMQPMessage $rabbitMessage) use ($callback): void {
                    $this->handleDelivery($rabbitMessage, $callback);
                },
            );

            while (count($this->getChannel()->callbacks)) {
                $this->getChannel()->wait(null, true);
                $this->checkShutdown();
                if (!$this->shouldProcessNext()) {
                    break 2;
                }
                if ($this->refreshInterval !== 0) {
                    sleep($this->refreshInterval);
                }
            }
        }

        $this->getChannel()->close();
        $this->connection->close();
    }

    /**
     * Acknowledge the delivery only after the callback finished.
     *
     * A message that cannot be unserialized is rejected without requeue (it goes to a dead-letter
     * exchange when the queue has one). A failing callback requeues the message for another attempt.
     *
     * @throws SerializeException
     * @throws ShutdownException
     */
    private function handleDelivery(AMQPMessage $rabbitMessage, Closure $callback): void
    {
        try {
            $message = $this->serializer->unserialize($rabbitMessage->getBody());
        } catch (SerializeException $e) {
            $rabbitMessage->nack(false);
            throw $e;
        }

        try {
            $callback($message);
        } catch (ShutdownException $e) {
            // The dispatcher asks to stop after the message was handled.
            $rabbitMessage->ack();
            throw $e;
        } catch (Throwable $e) {
            $rabbitMessage->nack(true);
            throw $e;
        }
        $rabbitMessage->ack();
        $this->incrementProcessedItems();
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
