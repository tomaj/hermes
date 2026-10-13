<?php

declare(strict_types=1);

use App\Hermes;
use App\WelcomeEmailHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\PredisShutdown;

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger('worker', [new StreamHandler('php://stdout')]);

$redis = Hermes::redis();
$driver = Hermes::driver($redis);
$driver->setShutdown(new PredisShutdown($redis)); // php bin/restart.php stops every worker
$driver->setMaxProcessItems(1000);                 // exit after 1000 messages, Supervisor starts a fresh one

$mailer = new Mailer(Transport::fromDsn(getenv('MAILER_DSN') ?: 'smtp://127.0.0.1:1025'));

$dispatcher = new Dispatcher($driver, $logger);
$dispatcher->registerHandler('user.registered', new WelcomeEmailHandler($mailer));

$logger->info('Worker started, waiting for messages');
$dispatcher->handle();
