<?php

namespace Tomaj\Hermes\Driver;

use Exception;
use Tomaj\Hermes\MessageInterface;
use Closure;
use Tomaj\Hermes\MessageSerializer;
use InvalidArgumentException;

class RedisSetDriver implements DriverInterface
{
    use MaxItemsTrait;
    use SerializerAwareTrait;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $scheduleKey;

    /**
     * @var Redis|Predis\Client
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
     * @param Redis\Predis\Client    $redis
     * @param string                 $key
     * @param integer                $refreshInterval
     */
    public function __construct($redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')
    {
        if (!(($redis instanceof \Predis\Client) || ($redis instanceof \Redis))) {
            throw new InvalidArgumentException('Predis\Client or Redis instance required');
        }

        $this->key = $key;
        $this->scheduleKey = $scheduleKey;
        $this->redis = $redis;
        $this->refreshInterval = $refreshInterval;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message): bool
    {
        if ($message->getExecuteAt() && $message->getExecuteAt() > microtime(true)) {
            $this->redis->zadd($this->scheduleKey, $message->getExecuteAt(), $this->serializer->serialize($message));
        } else {
            $this->redis->sadd($this->key, $this->serializer->serialize($message));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function wait(Closure $callback): void
    {
        while (true) {
            if (!$this->shouldProcessNext()) {
                break;
            }
            while (true) {
                // check schedule
                $messageString = false;
                if ($this->redis instanceof \Predis\Client) {
                    $messagesString = $this->redis->zrangebyscore($this->scheduleKey, '-inf', microtime(true), 'LIMIT', 0, 1);
                    if (count($messagesString)) {
                        foreach ($messagesString as $messageString) {
                            $this->redis->zrem($this->scheduleKey, $messageString);
                        }
                    }
                }
                if ($this->redis instanceof \Redis) {
                    $messagesString = $this->redis->zRangeByScore($this->scheduleKey, '-inf', microtime(true), ['limit' => [0, 1]]);
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
                
                if ($this->redis instanceof \Predis\Client) {
                    $messageString = $this->redis->spop($this->key);
                }
                if ($this->redis instanceof \Redis) {
                    $messageString = $this->redis->sPop($this->key);
                }
                
                if (!$messageString) {
                    break;
                }

                $callback($this->serializer->unserialize($messageString));
                $this->incrementProcessedItems();
            }

            if ($this->refreshInterval) {
                sleep($this->refreshInterval);
            }
        }
    }
}
