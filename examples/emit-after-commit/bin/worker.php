<?php

declare(strict_types=1);

use App\Hermes;
use App\WelcomeHandler;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

require __DIR__ . '/../vendor/autoload.php';

$driver = Hermes::driver();
$driver->setShutdown(new SharedFileShutdown(Hermes::directory() . '/shutdown'));

$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('user.registered', new WelcomeHandler(Hermes::database(), Hermes::directory() . '/welcome.log'));
$dispatcher->handle();
