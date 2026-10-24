<?php

declare(strict_types=1);

/*
 * One worker process for the concurrent schedule promotion tests.
 * Usage: php schedule-worker.php <redis|predis> <redis url> <key prefix>
 * Prints the id of every message it handles, one per line, and exits once the
 * schedule and the queue are both empty.
 */
use Predis\Client;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\ShutdownException;
use Tomaj\Hermes\Test\Driver\Fixture\DrainedShutdown;

require __DIR__ . '/../../../vendor/autoload.php';

[, $driverName, $url, $prefix] = $argv;

$parts = parse_url($url);
$host = is_array($parts) && isset($parts['host']) ? $parts['host'] : '127.0.0.1';
$port = is_array($parts) && isset($parts['port']) ? $parts['port'] : 6379;

if ($driverName === 'redis') {
    $client = new Redis();
    $client->connect($host, $port);
    $driver = new RedisSetDriver($client, $prefix, 0, $prefix . '_schedule');
    $count = static fn(): int => (int) $client->zCard($prefix . '_schedule') + $client->scard($prefix);
} else {
    $client = new Client($url);
    $driver = new PredisSetDriver($client, $prefix, 0, $prefix . '_schedule');
    $count = static fn(): int => $client->zcard($prefix . '_schedule') + $client->scard($prefix);
}

$driver->setShutdown(new DrainedShutdown($count));
try {
    $driver->wait(static function (MessageInterface $message): bool {
        echo $message->getId(), "\n";
        return true;
    });
} catch (ShutdownException) {
    // drained
}
