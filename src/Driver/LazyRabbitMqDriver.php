<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionBlockedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPOutOfBoundsException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;

final class LazyRabbitMqDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    private ?AMQPChannel $channel = null;

    /**
     * @param array<string, mixed> $amqpMessageProperties
     * @param bool $durable Declare a durable queue and publish persistent messages (delivery_mode 2).
     *                      Pass false to keep using a queue that was declared non-durable by Hermes 5.x.
     *                      RabbitMQ 4.3+ refuses non-durable queues unless
     *                      deprecated_features.permit.transient_nonexcl_queues is set.
     */
    public function __construct(
        private readonly AbstractConnection $connection,
        private readonly string $queue,
        private readonly array $amqpMessageProperties = [],
        private readonly int $refreshInterval = 0,
        private readonly string $consumerTag = 'hermes',
        private readonly bool $durable = true,
    ) {
        $this->serializer = new MessageSerializer();
    }

    /**
     * @throws AMQPChannelClosedException
     * @throws AMQPConnectionBlockedException
     * @throws AMQPConnectionClosedException
     * @throws AMQPTimeoutException
     */
    #[\Override]
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
     * @throws NotSupportedException
     */
    #[\Override]
    public function setupPriorityQueue(string $name, int $priority): never
    {
        throw new NotSupportedException('LazyRabbitMqDriver is not supporting priority queues now');
    }

    /**
     * @throws ShutdownException
     * @throws AMQPOutOfBoundsException
     * @throws AMQPRuntimeException
     * @throws AMQPTimeoutException
     * @throws \ErrorException
     */
    #[\Override]
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

            while ($this->getChannel()->is_consuming()) {
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
     * @throws AMQPTimeoutException
     */
    private function getChannel(): AMQPChannel
    {
        if ($this->channel instanceof AMQPChannel) {
            return $this->channel;
        }
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($this->queue, false, $this->durable, false, false);
        return $this->channel;
    }
}
