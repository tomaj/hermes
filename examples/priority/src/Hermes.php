<?php

declare(strict_types=1);

namespace App;

use Predis\Client;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\PredisSetDriver;

final class Hermes
{
    public const LOW = Dispatcher::DEFAULT_PRIORITY;          // 100, the queue the driver always has
    public const NORMAL = Dispatcher::DEFAULT_PRIORITY + 50;  // 150
    public const HIGH = Dispatcher::DEFAULT_PRIORITY + 100;   // 200

    public static function driver(?Client $redis = null): PredisSetDriver
    {
        $driver = new PredisSetDriver($redis ?? new Client(getenv('REDIS_URL') ?: 'tcp://127.0.0.1:6379'), 'hermes', 1);

        // The emitter and every worker must register the same queues.
        // Register them in ascending order: the worker polls them in reverse registration order.
        $driver->setupPriorityQueue('hermes_normal', self::NORMAL);
        $driver->setupPriorityQueue('hermes_high', self::HIGH);

        return $driver;
    }
}
