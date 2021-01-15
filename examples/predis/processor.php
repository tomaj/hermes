<?php
declare(strict_types=1);

use Predis\Client;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Shutdown\PredisShutdown;

require_once __DIR__.'/../../vendor/autoload.php';

$redis = new Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
]);
$driver = new PredisSetDriver($redis, 'hermes', 1);
$driver->setShutdown(new PredisShutdown($redis));
$driver->setupPriorityQueue('hermes_low', \Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', \Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY + 10);

$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('type1', new EchoHandler());

$dispatcher->handle();
//$dispatcher->handle([Dispatcher::PRIORITY_HIGH]);
