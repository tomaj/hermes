<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use InvalidArgumentException;
use Predis\Client;
use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Restart\RestartException;

class RedisSetDriver implements DriverInterface
{
    use MaxItemsTrait;
    use RestartTrait;
    use SerializerAwareTrait;

    private $queues = [];

    /**
     * @var string
     */
    private $scheduleKey;

    /**
     * @var Redis|Client
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
     * of this class. You can use native Redis php extension or Predis extension.
     *
     * @see examples/redis
     *
     * @param Redis|Client    $redis
     * @param string                 $key
     * @param integer                $refreshInterval
     * @param string                 $scheduleKey
     */
    public function __construct($redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')
    {
        if (!(($redis instanceof Client) || ($redis instanceof Redis))) {
            throw new InvalidArgumentException('Predis\Client or Redis instance required');
        }

        $this->setupPriorityQueue($key, Dispatcher::PRIORITY_MEDIUM);

        $this->scheduleKey = $scheduleKey;
        $this->redis = $redis;
        $this->refreshInterval = $refreshInterval;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message, int $priority = Dispatcher::PRIORITY_MEDIUM): bool
    {
        if ($message->getExecuteAt() && $message->getExecuteAt() > microtime(true)) {
            $this->redis->zadd($this->scheduleKey, [$message->getExecuteAt(), $this->serializer->serialize($message)]);
        } else {
            $key = $this->getKey($priority);
            $this->redis->sadd($key, $this->serializer->serialize($message));
        }
        return true;
    }

    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->queues[$priority] = $name;
        ksort($this->queues, SORT_ASC | SORT_NUMERIC);
    }

    private function getKey(int $priority): string
    {
        if (!isset($this->queues[$priority])) {
            throw new \Exception("Unknown priority {$priority}");
        }
        return $this->queues[$priority];
    }

    /**s
     * {@inheritdoc}
     *
     * @throws RestartException
     */
    public function wait(Closure $callback, array $priorities = []): void
    {
        $queues = array_reverse($this->queues, true);
        while (true) {
            $this->checkRestart();
            if (!$this->shouldProcessNext()) {
                break;
            }

            // check schedule
            $messagesString = [];
            if ($this->redis instanceof Client) {
                $messagesString = $this->redis->zrangebyscore($this->scheduleKey, '-inf', microtime(true), ['LIMIT' => ['OFFSET' => 0, 'COUNT' => 1]]);
                if (count($messagesString)) {
                    foreach ($messagesString as $messageString) {
                        $this->redis->zrem($this->scheduleKey, $messageString);
                    }
                }
            }
            if ($this->redis instanceof Redis) {
                $messagesString = $this->redis->zRangeByScore($this->scheduleKey, '-inf', (string)microtime(true), ['limit' => [0, 1]]);
                if (count($messagesString)) {
                    foreach ($messagesString as $messageString) {
                        $this->redis->zRem($this->scheduleKey, $messageString);
                    }
                }
            }
            if (count($messagesString)) {
                foreach ($messagesString as $messageString) {
                    $this->send($this->serializer->unserialize($messageString));
                }
            }

            $messageString = false;
            $foundPriority = null;

            foreach ($queues as $priority => $name) {
                if (count($priorities) > 0 && !in_array($priority, $priorities)) {
                    continue;
                }
                if ($messageString) {
                    break;
                }

                $key = $this->getKey($priority);

                if ($this->redis instanceof Client) {
                    $messageString = $this->redis->spop($key);
                    $foundPriority = $priority;
                }
                if ($this->redis instanceof Redis) {
                    $messageString = $this->redis->sPop($key);
                    $foundPriority = $priority;
                }
            }

            if ($messageString) {
                $message = $this->serializer->unserialize($messageString);
                $callback($message, $foundPriority);
                $this->incrementProcessedItems();
            } else {
                if ($this->refreshInterval) {
                    $this->checkRestart();
                    sleep($this->refreshInterval);
                }
            }
        }
    }
}
