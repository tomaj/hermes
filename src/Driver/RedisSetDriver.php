<?php

namespace Tomaj\Hermes\Driver;

use Exception;
use Tomaj\Hermes\Message;
use Closure;
use Redis;
use Tomaj\Hermes\MessageSerializer;

class RedisSetDriver implements DriverInterface
{
    private $key;

    private $host;

    private $port;

    private $database;

    private $timeout;

    private $serializer;

    public function __construct($key = 'hermes', $host = 'localhost', $port = 6379, $database = 0, $timeout = 1)
    {
        if (!extension_loaded('redis')) {
            throw new Exception('Redis extension must be loaded');
        }

        $this->key = $key;
        $this->host = $host;
        $this->port = $port;
        $this->database = $database;
        $this->timeout = $timeout;
        $this->serializer = new MessageSerializer();
    }

    private function getRedis()
    {
        $redis = new Redis();
        $redis->connect($this->host, $this->port);
        if ($this->database) {
            $redis->select($this->database);
        }
        return $redis;
    }

    public function send(Message $message)
    {
        $redis = $this->getRedis();
        $redis->sadd($this->key, $this->serializer->serialize($message));
    }

    public function wait(Closure $callback)
    {
        while (true) {
            sleep($this->timeout);

            $redis = $this->getRedis();
            while (true) {
                $messageString = $redis->sPop($this->key);
                if (!$messageString) {
                    break;
                }

                $callback($this->serializer->unserialize($messageString));
            }
        }
    }
}
