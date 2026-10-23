<?php

declare(strict_types=1);

use App\Hermes;
use App\OrderHandler;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

require __DIR__ . '/../vendor/autoload.php';

$var = getenv('APP_VAR') ?: __DIR__ . '/../var';
@mkdir($var, 0o775, true);

$driver = Hermes::workerDriver();
$driver->setShutdown(new SharedFileShutdown("{$var}/shutdown"));

$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('order.placed', new OrderHandler("{$var}/orders.log"));
$dispatcher->handle();
