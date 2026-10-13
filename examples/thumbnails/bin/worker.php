<?php

declare(strict_types=1);

use App\Hermes;
use App\ThumbnailHandler;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

require __DIR__ . '/../vendor/autoload.php';

$directory = __DIR__ . '/../storage/thumbnails';
@mkdir($directory, 0o775, true);

$driver = Hermes::driver();
// RabbitMQ has no Hermes shutdown helper of its own. A shared file works with any driver.
$driver->setShutdown(new SharedFileShutdown('/tmp/hermes-thumbnails-shutdown'));
$driver->setMaxProcessItems(500); // GD leaks a little with big images, start fresh now and then

$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('image.thumbnails', new ThumbnailHandler($directory));
$dispatcher->handle();
