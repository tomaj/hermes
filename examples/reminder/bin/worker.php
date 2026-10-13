<?php

declare(strict_types=1);

use App\Hermes;
use App\ReminderHandler;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\PredisShutdown;

require __DIR__ . '/../vendor/autoload.php';

$redis = Hermes::redis();
$driver = Hermes::driver($redis);
$driver->setShutdown(new PredisShutdown($redis));

$mailer = new Mailer(Transport::fromDsn(getenv('MAILER_DSN') ?: 'smtp://127.0.0.1:1025'));

$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('reservation.remind', new ReminderHandler($mailer));
$dispatcher->handle();
