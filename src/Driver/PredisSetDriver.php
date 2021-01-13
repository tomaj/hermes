<?php
declare(strict_types=1);

namespace Tomaj\Hermes\Driver;

use Closure;
use Predis\Client;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use Tomaj\Hermes\Shutdown\ShutdownException;

class PredisSetDriver implements DriverInterface
{
    use MaxItemsTrait;
    use ShutdownTrait;
    use SerializerAwareTrait;

    private $queues = [];

    /**
     * @var string
     */
    private $scheduleKey;

    /**
     * @var Client
     */
    private $redis;

    /**
     * @var integer
     */
    private $refreshInterval;

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
     *
     * @param Client                 $redis
     * @param string                 $key
     * @param integer                $refreshInterval
     * @param string                 $scheduleKey
     */
    public function __construct(Client $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')
    {
        $this->setupPriorityQueue($key, Dispatcher::PRIORITY_MEDIUM);

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
    public function send(MessageInterface $message, int $priority = Dispatcher::PRIORITY_MEDIUM): bool
    {
        if ($message->getExecuteAt() && $message->getExecuteAt() > microtime(true)) {
            $this->redis->zadd($this->scheduleKey, [$message->getExecuteAt(), $this->serializer->serialize($message)]);
        } else {
            $key = $this->getKey($priority);
            $this->redis->sadd($key, [$this->serializer->serialize($message)]);
        }
        return true;
    }

    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->queues[$priority] = $name;
        ksort($this->queues, SORT_ASC | SORT_NUMERIC);
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

    /**s
     * {@inheritdoc}
     *
     * @throws ShutdownException
     * @throws UnknownPriorityException
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
            $messagesString = $this->redis->zrangebyscore($this->scheduleKey, '-inf', microtime(true), ['LIMIT' => ['OFFSET' => 0, 'COUNT' => 1]]);
            if (count($messagesString)) {
                foreach ($messagesString as $messageString) {
                    $this->redis->zrem($this->scheduleKey, $messageString);
                    $this->send($this->serializer->unserialize($messageString));
                }
            }

            $messageString = null;
            $foundPriority = null;

            foreach ($queues as $priority => $name) {
                if (count($priorities) > 0 && !in_array($priority, $priorities)) {
                    continue;
                }
                if ($messageString !== null) {
                    break;
                }

                $key = $this->getKey($priority);

                $messageString = $this->redis->spop($key);
                $foundPriority = $priority;
            }

            if ($messageString !== null) {
                $message = $this->serializer->unserialize($messageString);
                $callback($message, $foundPriority);
                $this->incrementProcessedItems();
            } else {
                if ($this->refreshInterval) {
                    $this->checkShutdown();
                    sleep($this->refreshInterval);
                }
            }
        }
    }
}
