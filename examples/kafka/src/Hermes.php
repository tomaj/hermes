<?php

declare(strict_types=1);

namespace App;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;
use Tomaj\Hermes\Driver\KafkaDriver;

final class Hermes
{
    public const string TOPIC = 'orders';

    public static function brokers(): string
    {
        return getenv('KAFKA_BROKERS') ?: '127.0.0.1:9092';
    }

    /**
     * The web process only sends, so it needs no consumer.
     */
    public static function emitterDriver(): KafkaDriver
    {
        return new KafkaDriver(self::producer(), null, self::TOPIC);
    }

    /**
     * Every worker with the same group.id shares the partitions of the topic.
     */
    public static function workerDriver(): KafkaDriver
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', self::brokers());
        $conf->set('group.id', getenv('KAFKA_GROUP') ?: 'order-workers');
        // Hermes commits each message after the handler; never let librdkafka commit on its own
        $conf->set('enable.auto.commit', 'false');
        // a new group starts at the oldest message instead of only new ones
        $conf->set('auto.offset.reset', 'earliest');

        // the producer is for retries, which the dispatcher sends back to the topic
        return new KafkaDriver(self::producer(), new KafkaConsumer($conf), self::TOPIC);
    }

    private static function producer(): Producer
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', self::brokers());
        return new Producer($conf);
    }
}
