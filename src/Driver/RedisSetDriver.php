<?php

namespace Tomaj\Hermes\Driver;

use Exception;
use Tomaj\Hermes\Message;
use Closure;
use Redis;
use Predis;
use Tomaj\Hermes\MessageSerializer;
use InvalidArgumentException;

class RedisSetDriver implements DriverInterface
{
    use MaxItemsTrait;
    // use SerializerAwareTrait;

    private $key;

    private $redis;

    private $serializer;

    private $refreshInterval;

    public function __construct($redis, $key = 'hermes', $refreshInterval = 1)
    {
        if (!(($redis instanceof Predis\Client) || ($redis instanceof Redis))) {
            throw new InvalidArgumentException('Predis\Client or Redis instance required');
        }

        $this->key = $key;
        $this->redis = $redis;
        $this->refreshInterval = $refreshInterval;
        $this->serializer = new MessageSerializer();
    }

    /**
     * {@inheritdoc}
     */
    public function send(Message $message)
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
                if ($this->redis instanceof Predis\Client) {
                    $messageString = $this->redis->spop($this->key);
                } else {
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
