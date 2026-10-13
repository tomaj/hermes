<?php

declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\Shutdown\ShutdownException;

class RedisSetDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    /** @var array<int, string> */
    private array $queues = [];

    /**
     * Create new RedisSetDriver
     *
     * This driver is using redis set. With send message it add new item to set
     * and in wait() command it is reading new items in this set.
     * This driver doesn't use redis pubsub functionality, only redis sets.
     *
     * Managing connection to redis is up to you and you have to create it outsite
     * of this class. You have to have enabled native Redis php extension.
     *
     * @see examples/redis
     */
    public function __construct(
        private readonly Redis $redis,
        string $key = 'hermes',
        private readonly int $refreshInterval = 1,
        private readonly string $scheduleKey = 'hermes_schedule',
    ) {
        $this->setupPriorityQueue($key, Dispatcher::DEFAULT_PRIORITY);
        $this->serializer = new MessageSerializer();
    }

    /**
     * @throws UnknownPriorityException
     */
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        if ($message->getExecuteAt() !== null && $message->getExecuteAt() > microtime(true)) {
            $this->redis->zAdd($this->scheduleKey, $message->getExecuteAt(), $this->serializer->serialize($message));
        } else {
            $key = $this->getKey($priority);
            $this->redis->sAdd($key, $this->serializer->serialize($message));
        }
        return true;
    }

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

            // check schedule
            $microTime = microtime(true);
            /** @var list<string> $messageStrings */
            $messageStrings = $this->redis->zRangeByScore($this->scheduleKey, '-inf', (string) microtime(true), ['limit' => [0, 1]]);
            foreach ($messageStrings as $messageString) {
                // ZREM is atomic: only the worker that actually removed the entry re-sends it
                if ($this->redis->zRem($this->scheduleKey, $messageString) === 1) {
                    $this->send($this->serializer->unserialize($messageString));
                }
            }

            $messageString = null;
            $foundPriority = null;

            foreach ($queues as $priority => $name) {
                if (count($priorities) > 0 && !in_array($priority, $priorities, true)) {
                    continue;
                }
                if ($messageString !== null) {
                    break;
                }

                $messageString = $this->pop($this->getKey($priority));
                $foundPriority = $priority;
            }

            if ($messageString !== null) {
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

    private function pop(string $key): ?string
    {
        $messageString = $this->redis->sPop($key);
        if (is_string($messageString) && $messageString !== '') {
            return $messageString;
        }

        return null;
    }
}
