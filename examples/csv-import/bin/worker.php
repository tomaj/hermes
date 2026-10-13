<?php

declare(strict_types=1);

use App\Hermes;
use App\ImportChunkHandler;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\PredisShutdown;

require __DIR__ . '/../vendor/autoload.php';

if (!is_dir(__DIR__ . '/../storage')) {
    mkdir(__DIR__ . '/../storage', 0o775, true);
}

$db = new PDO('sqlite:' . __DIR__ . '/../storage/app.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE IF NOT EXISTS customers (email TEXT PRIMARY KEY, name TEXT NOT NULL)');

$redis = Hermes::redis();
$driver = Hermes::driver($redis);
$driver->setShutdown(new PredisShutdown($redis));

$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('csv.import_chunk', new ImportChunkHandler($db));
$dispatcher->handle();
