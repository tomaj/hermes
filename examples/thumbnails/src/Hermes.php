<?php

declare(strict_types=1);

namespace App;

use PhpAmqpLib\Connection\AMQPLazyConnection;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;

final class Hermes
{
    public static function driver(): LazyRabbitMqDriver
    {
        // Lazy: the TCP connection opens on the first send() or wait(), not here.
        $connection = new AMQPLazyConnection(getenv('RABBITMQ_HOST') ?: '127.0.0.1', 5672, 'guest', 'guest');

        // Queue "thumbnails", no extra AMQP message properties, sleep 1 second between empty polls.
        return new LazyRabbitMqDriver($connection, 'thumbnails', [], 1);
    }
}
