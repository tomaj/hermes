<?php

declare(strict_types=1);

use App\ExportHandler;
use App\Hermes;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

require __DIR__ . '/../vendor/autoload.php';

$driver = Hermes::driver();
// php bin/shutdown.php touches this file; workers started before that exit
$driver->setShutdown(new SharedFileShutdown(Hermes::directory() . '/shutdown'));

$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('report.export', new ExportHandler(Hermes::directory() . '/exports.log'));
$dispatcher->handle();
