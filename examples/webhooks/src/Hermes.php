<?php

declare(strict_types=1);

namespace App;

use Predis\Client;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Emitter;

final class Hermes
{
    public static function redis(): Client
    {
        return new Client(getenv('REDIS_URL') ?: 'tcp://127.0.0.1:6379');
    }

    public static function driver(Client $redis): PredisSetDriver
    {
        // Queue key "hermes", poll once a second, delayed messages and retries in "hermes_schedule".
        return new PredisSetDriver($redis, 'hermes', 1, 'hermes_schedule');
    }

    public static function emitter(): Emitter
    {
        return new Emitter(self::driver(self::redis()));
    }
}
