<?php

declare(strict_types=1);

namespace App\Hermes;

use Aws\Sqs\SqsClient;
use InvalidArgumentException;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use Predis\Client;
use Redis;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;

/**
 * Builds the Hermes driver from plain configuration, so the web process and the worker
 * always use the same driver, queue name and connection.
 */
final class DriverFactory
{
    /**
     * @param array{
     *     driver: string,
     *     queue: string,
     *     max_items: int,
     *     redis: array{host: string, port: int},
     *     rabbitmq: array{host: string, port: int, user: string, password: string, vhost: string},
     *     sqs: array{region: string, endpoint: ?string, key: string, secret: string},
     * } $config
     */
    public static function create(array $config): DriverInterface
    {
        $queue = $config['queue'];

        $driver = match ($config['driver']) {
            'redis' => new RedisSetDriver(self::redis($config['redis']), $queue),
            'predis' => new PredisSetDriver(new Client(['host' => $config['redis']['host'], 'port' => $config['redis']['port']]), $queue),
            'rabbitmq' => new LazyRabbitMqDriver(
                new AMQPLazyConnection(
                    $config['rabbitmq']['host'],
                    $config['rabbitmq']['port'],
                    $config['rabbitmq']['user'],
                    $config['rabbitmq']['password'],
                    $config['rabbitmq']['vhost'],
                ),
                $queue,
                [],
                1, // seconds to sleep when the queue is empty
            ),
            'sqs' => new AmazonSqsDriver(self::sqs($config['sqs']), $queue),
            default => throw new InvalidArgumentException("Unknown Hermes driver \"{$config['driver']}\""),
        };

        // Start a fresh worker process after this many messages.
        $driver->setMaxProcessItems($config['max_items']);

        return $driver;
    }

    /**
     * @param array{host: string, port: int} $config
     */
    private static function redis(array $config): Redis
    {
        $redis = new Redis();
        $redis->connect($config['host'], $config['port']);

        return $redis;
    }

    /**
     * @param array{region: string, endpoint: ?string, key: string, secret: string} $config
     */
    private static function sqs(array $config): SqsClient
    {
        $options = [
            'region' => $config['region'],
            'version' => 'latest',
            'credentials' => ['key' => $config['key'], 'secret' => $config['secret']],
        ];
        if ($config['endpoint'] !== null && $config['endpoint'] !== '') {
            $options['endpoint'] = $config['endpoint']; // a local SQS such as moto or ElasticMQ
        }

        return new SqsClient($options);
    }
}
