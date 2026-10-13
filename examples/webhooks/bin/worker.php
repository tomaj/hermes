<?php

declare(strict_types=1);

use App\Hermes;
use App\WebhookHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpClient\HttpClient;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Shutdown\PredisShutdown;

require __DIR__ . '/../vendor/autoload.php';

$redis = Hermes::redis();
$driver = Hermes::driver($redis);
$driver->setShutdown(new PredisShutdown($redis));

$dispatcher = new Dispatcher($driver, new Logger('webhooks', [new StreamHandler('php://stdout')]));
$dispatcher->registerHandler('webhook.deliver', new WebhookHandler(HttpClient::create()));
$dispatcher->handle();
