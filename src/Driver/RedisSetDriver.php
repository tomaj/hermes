<?php

namespace Tomaj\Hermes\Driver;

use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;
use InvalidArgumentException;
use Closure;

class RedisSetDriver implements DriverInterface
{
    use MaxItemsTrait;
    use SerializerAwareTrait;

    /**
     * @var string
     */
    private $key;

    /**
     * @var \Redis\Predis\Client|Redis|\RedisProxy\RedisProxy
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
     * @param \Redis\Predis\Client|Redis|\RedisProxy\RedisProxy    $redis
     * @param string                                               $key
     * @param integer                                              $refreshInterval
     */
    public function __construct($redis, $key = 'hermes', $refreshInterval = 1)
    {
        if (!(($redis instanceof \Predis\Client) || ($redis instanceof \Redis) || ($redis instanceof \RedisProxy\RedisProxy))) {
            throw new InvalidArgumentException('Predis\Client or Redis or RedisProxy instance required');
        }

        $this->key = $key;
        $this->redis = $redis;
        $this->refreshInterval = $refreshInterval;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(MessageInterface $message)
    {
        $this->redis->sadd($this->key, $this->serializer->serialize($message));
    }

    /**
     * {@inheritdoc}
     */
    public function wait(Closure $callback)
    {
        while (true) {
            if (!$this->shouldProcessNext()) {
                break;
            }
            while (true) {
                $messageString = false;

                if ($this->redis instanceof \Predis\Client) {
                    $messageString = $this->redis->spop($this->key);
                } else if ($this->redis instanceof \Redis) {
                    $messageString = $this->redis->sPop($this->key);
                } else if ($this->redis instanceof \RedisProxy\RedisProxy) {
                    $messageString = $this->redis->spop($this->key);
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
