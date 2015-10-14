<?php

namespace Tomaj\Hermes\Driver;

use Exception;
use Tomaj\Hermes\Message;
use Closure;
use Redis;
use Tomaj\Hermes\MessageSerializer;

class RedisPubSubDriver implements DriverInterface
{
    private $channel;

    private $host;

    private $port;

    private $serializer;

    public function __construct($channel = 'hermes', $host = 'localhost', $port = 6379)
    {
        if (!extension_loaded('redis')) {
            throw new Exception('Redis extension must be loaded');
        }

        $this->channel = $channel;
        $this->host = $host;
        $this->port = $port;

        $this->serializer = new MessageSerializer();
    }

    private function getRedis()
    {
        $redis = new Redis();
        $redis->connect($this->host, $this->port);
        return $redis;
    }

    public function send(Message $message)
    {
        $redis = $this->getRedis();
        $redis->publish($this->channel, $this->serializer->serialize($message));
    }

    public function wait(Closure $callback)
    {
        $redis = $this->getRedis();
        $redis->subscribe([$this->channel], function ($messageString) use ($callback) {
            $callback($this->serializer->unserialize($message));
        });
    }
}
