<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use Predis\Client;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;

final class PredisSetDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    /** @var array<int, string> */
    private array $queues = [];

    /**
     * Create new PredisSetDriver
     *
     * This driver is using redis set. With send message it add new item to set
     * and in wait() command it is reading new items in this set.
     * This driver doesn't use redis pubsub functionality, only redis sets.
     *
     * Managing connection to redis is up to you and you have to create it outsite
     * of this class. You will need to install predis php package.
     *
     * @see examples/redis
     */
    public function __construct(
        private readonly Client $redis,
        string $key = 'hermes',
        private readonly int $refreshInterval = 1,
        private readonly string $scheduleKey = 'hermes_schedule',
    ) {
        $this->setupPriorityQueue($key, Dispatcher::DEFAULT_PRIORITY);
        $this->serializer = new MessageSerializer();
    }

    /**
     * @throws UnknownPriorityException
     * @throws SerializeException
     */
    #[\Override]
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $executeAt = $message->getExecuteAt();
        if ($executeAt !== null && $executeAt > microtime(true)) {
            $this->redis->zadd($this->scheduleKey, [$this->serializer->serialize($message) => $executeAt]);
        } else {
            $key = $this->getKey($priority);
            $this->redis->sadd($key, [$this->serializer->serialize($message)]);
        }
        return true;
    }

    #[\Override]
    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->queues[$priority] = $name;
    }

    /**
     * @throws UnknownPriorityException
     */
    private function getKey(int $priority): string
    {
        if (!isset($this->queues[$priority])) {
            throw new UnknownPriorityException("Unknown priority {$priority}");
        }
        return $this->queues[$priority];
    }

    /**
     * @throws ShutdownException
     * @throws UnknownPriorityException
     * @throws SerializeException
     */
    #[\Override]
    public function wait(Closure $callback, array $priorities = []): void
    {
        // Highest priority first, regardless of the order the queues were set up in
        $queues = $this->queues;
        krsort($queues, SORT_NUMERIC);
        while (true) {
            $this->checkShutdown();
            if (!$this->shouldProcessNext()) {
                break;
            }

            // check schedule; Redis takes the score bound as a string, keep the microseconds
            $now = sprintf('%.6F', microtime(true));
            $messagesString = $this->redis->zrangebyscore($this->scheduleKey, '-inf', $now, ['LIMIT' => ['OFFSET' => 0, 'COUNT' => 1]]);
            if ($messagesString !== []) {
                foreach ($messagesString as $messageString) {
                    if (!is_string($messageString)) {
                        continue;
                    }
                    $this->redis->zrem($this->scheduleKey, $messageString);
                    $this->send($this->serializer->unserialize($messageString));
                }
            }

            $messageString = null;
            $foundPriority = null;

            foreach ($queues as $priority => $name) {
                if ($priorities !== [] && !in_array($priority, $priorities, true)) {
                    continue;
                }
                if ($messageString !== null) {
                    break;
                }

                $key = $this->getKey($priority);

                $popped = $this->redis->spop($key);
                if (is_string($popped)) {
                    $messageString = $popped;
                    $foundPriority = $priority;
                }
            }

            if (is_string($messageString)) {
                $message = $this->serializer->unserialize($messageString);
                $callback($message, $foundPriority);
                $this->incrementProcessedItems();
            } else {
                if ($this->refreshInterval !== 0) {
                    $this->checkShutdown();
                    sleep($this->refreshInterval);
                }
            }
        }
    }
}
