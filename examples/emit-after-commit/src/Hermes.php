<?php

declare(strict_types=1);

namespace App;

use Throwable;
use Tomaj\Hermes\BufferedEmitter;
use Tomaj\Hermes\Driver\FileSystemDriver;
use Tomaj\Hermes\Emitter;

final class Hermes
{
    public static function directory(): string
    {
        return getenv('HERMES_DIR') ?: __DIR__ . '/../var';
    }

    public static function database(): Database
    {
        @mkdir(self::directory(), 0o775, true);

        return new Database(self::directory() . '/app.sqlite');
    }

    public static function driver(): FileSystemDriver
    {
        return new FileSystemDriver(self::directory() . '/hermes', 'users', refreshInterval: 1);
    }

    /**
     * Emits right away outside a transaction; inside one, holds the messages until the commit.
     */
    public static function emitter(Database $database): BufferedEmitter
    {
        $emitter = new BufferedEmitter(new Emitter(self::driver()), [$database, 'isInTransaction']);

        $database->registerOnCommit(static function () use ($emitter): void {
            try {
                $emitter->flush();
            } catch (Throwable $e) {
                // the rows are committed, the unsent messages are still in the buffer
                error_log("hermes: flush after commit failed, retrying: {$e->getMessage()}");
                try {
                    $emitter->flush();
                } catch (Throwable $e) {
                    error_log(sprintf('hermes: dropping %d messages: %s', $emitter->discard(), $e->getMessage()));
                }
            }
        });
        $database->registerOnRollback([$emitter, 'discard']);

        return $emitter;
    }
}
