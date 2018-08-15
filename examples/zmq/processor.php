<?php
declare(strict_types=1);

use Tomaj\Hermes\Driver\ZeroMqDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Handler\EchoHandler;

require_once __DIR__.'/../../vendor/autoload.php';

// Prepare ZMQ server
$context = new ZMQContext(1);
$responder = new ZMQSocket($context, ZMQ::SOCKET_REP);
$responder->bind("tcp://*:5555");

$driver = new ZeroMqDriver($responder);

$dispatcher = new Dispatcher($driver);

$dispatcher->registerHandler('type1', new EchoHandler());

$dispatcher->handle();
