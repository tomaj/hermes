<?php
declare(strict_types=1);

use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Shutdown\RedisShutdown;

require_once __DIR__.'/../../vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis, 'hermes', 1);
$driver->setShutdown(new RedisShutdown($redis));
$driver->setupPriorityQueue('hermes_low', \Tomaj\Hermes\Dispatcher::PRIORITY_LOW);
$driver->setupPriorityQueue('hermes_high', \Tomaj\Hermes\Dispatcher::PRIORITY_HIGH);

$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('type1', new EchoHandler());

$dispatcher->handle();
//$dispatcher->handle([Dispatcher::PRIORITY_HIGH]);
