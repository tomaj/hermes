<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use InvalidArgumentException;
use RdKafka\KafkaConsumer;
use RdKafka\Message as KafkaMessage;
use RdKafka\Producer;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;

/**
 * Apache Kafka through ext-rdkafka (librdkafka).
 *
 * send() produces the message to one topic, keyed by the message id, and waits until Kafka confirmed it.
 * wait() subscribes the consumer to the topic and commits the offset of a message only after the callback
 * returned, so delivery is at least once. The consumer must be created with `enable.auto.commit=false`
 * and a `group.id`; all workers with the same group share the partitions of the topic.
 *
 * Kafka has no priorities and no delayed delivery: setupPriorityQueue() throws, and a message with a
 * future executeAt is delivered at once.
 */
final class KafkaDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    private bool $subscribed = false;

    /**
     * @param Producer $producer sends messages; the worker needs it too, for retries
     * @param KafkaConsumer|null $consumer receives messages in wait(); an emitter can leave it out
     * @param string $topic topic of the queue
     * @param int $consumeTimeout milliseconds wait() blocks for the next message before it checks shutdown again
     * @param int $flushTimeout milliseconds send() waits for Kafka to confirm the message
     * @throws InvalidArgumentException when the topic is empty or a timeout is not positive
     */
    public function __construct(
        private readonly Producer $producer,
        private readonly ?KafkaConsumer $consumer = null,
        private readonly string $topic = 'hermes',
        private readonly int $consumeTimeout = 1000,
        private readonly int $flushTimeout = 10000,
    ) {
        if ($topic === '') {
            throw new InvalidArgumentException('The Kafka topic must not be empty');
        }
        if ($consumeTimeout < 1 || $flushTimeout < 1) {
            throw new InvalidArgumentException('consumeTimeout and flushTimeout must be positive');
        }
        $this->serializer = new MessageSerializer();
    }

    /**
     * @throws KafkaDriverException when Kafka did not confirm the message within the flush timeout
     * @throws SerializeException
     */
    #[\Override]
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $this->producer->newTopic($this->topic)->produce(\RD_KAFKA_PARTITION_UA, 0, $this->serializer->serialize($message), $message->getId());
        $result = $this->producer->flush($this->flushTimeout);
        if ($result !== \RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new KafkaDriverException("Kafka did not confirm message {$message->getId()} on topic {$this->topic}: " . rd_kafka_err2str($result), $result);
        }
        return true;
    }

    /**
     * @throws NotSupportedException
     */
    #[\Override]
    public function setupPriorityQueue(string $name, int $priority): never
    {
        throw new NotSupportedException('Kafka is not supporting priority queues now');
    }

    /**
     * @throws KafkaDriverException when no consumer was given or the consumer reports an error
     * @throws SerializeException
     * @throws ShutdownException
     */
    #[\Override]
    public function wait(Closure $callback, array $priorities = []): void
    {
        $consumer = $this->consumer ?? throw new KafkaDriverException('KafkaDriver needs a KafkaConsumer to wait for messages');
        if (!$this->subscribed) {
            $consumer->subscribe([$this->topic]);
            $this->subscribed = true;
        }

        while (true) {
            $this->checkShutdown();
            if (!$this->shouldProcessNext()) {
                break;
            }

            $message = $consumer->consume($this->consumeTimeout);
            if ($message->err === \RD_KAFKA_RESP_ERR_NO_ERROR) {
                $this->process($consumer, $message, $callback);
            } elseif ($message->err !== \RD_KAFKA_RESP_ERR__TIMED_OUT && $message->err !== \RD_KAFKA_RESP_ERR__PARTITION_EOF) {
                throw new KafkaDriverException("Kafka consumer error on topic {$this->topic}: {$message->errstr()}", $message->err);
            }
        }
    }

    /**
     * @throws SerializeException
     * @throws ShutdownException
     */
    private function process(KafkaConsumer $consumer, KafkaMessage $kafkaMessage, Closure $callback): void
    {
        try {
            $message = $this->serializer->unserialize($kafkaMessage->payload ?? '');
        } catch (SerializeException $e) {
            // It would fail the same way on every delivery and block its partition: skip it.
            $consumer->commit($kafkaMessage);
            throw $e;
        }

        try {
            $callback($message);
        } catch (ShutdownException $e) {
            // The dispatcher asks to stop after the message was handled.
            $consumer->commit($kafkaMessage);
            throw $e;
        }
        // Any other exception leaves the offset uncommitted: the group delivers the message again
        // after the worker restarts.
        $consumer->commit($kafkaMessage);
        $this->incrementProcessedItems();
    }
}
