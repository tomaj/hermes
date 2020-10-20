<?php
declare(strict_types=1);

use PhpAmqpLib\Connection\AMQPLazyConnection;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\EchoHandler;

require_once __DIR__.'/../../vendor/autoload.php';

$queueName = 'hermes_queue';
$connection = new AMQPLazyConnection('localhost', 5672, 'guest', 'guest', '/');
$driver = new LazyRabbitMqDriver($connection, $queueName, [], 0);

$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('type1', new EchoHandler());

$dispatcher->handle();
