<?php

declare(strict_types=1);

namespace App;

use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\FileSystemDriver;

final class Hermes
{
    public const int PRIORITY_HIGH = Dispatcher::DEFAULT_PRIORITY + 10;

    public static function directory(): string
    {
        return getenv('HERMES_DIR') ?: __DIR__ . '/../var/hermes';
    }

    public static function driver(): FileSystemDriver
    {
        // <dir>/<queue>/{tmp,new,processing,failed}, created on first use
        $driver = new FileSystemDriver(self::directory(), 'exports', refreshInterval: 1, visibilityTimeout: 300);
        $driver->setupPriorityQueue('exports_high', self::PRIORITY_HIGH);

        return $driver;
    }
}
