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

    /** @var array<int, string>  */
    private $queues = [];

    /**
     * @var string
     */
    private $scheduleKey;

    /**
     * @var Redis
     */
    private $redis;

    /**
     * @var integer
     */
    private $refreshInterval;

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
     *
     * @param Redis                  $redis
     * @param string                 $key
     * @param integer                $refreshInterval
     * @param string                 $scheduleKey
     */
    public function __construct(Redis $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')
    {
        $this->setupPriorityQueue($key, Dispatcher::DEFAULT_PRIORITY);

        $this->scheduleKey = $scheduleKey;
        $this->redis = $redis;
        $this->refreshInterval = $refreshInterval;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     *
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

    /**
     * {@inheritdoc}
     */
    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->queues[$priority] = $name;
    }

    /**
     * @param int $priority
     * @return string
     *
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
     * {@inheritdoc}
     *
     * @throws ShutdownException
     * @throws UnknownPriorityException
     * @throws SerializeException
     */
    public function wait(Closure $callback, array $priorities = []): void
    {
        $queues = array_reverse($this->queues, true);
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
