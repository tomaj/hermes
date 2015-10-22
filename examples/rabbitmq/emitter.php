<?php

use Tomaj\Hermes\Driver\RabbitMqDriver;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Message;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require_once __DIR__ . '/../../vendor/autoload.php';


$queueName = 'hermes_queue';
$connection = new AMQPStreamConnection('localhost', 5672, 'stanka', '123', '/stanka');
$channel = $connection->channel();
$channel->queue_declare($queueName, false, false, false, false);
$driver = new RabbitMqDriver($channel, $queueName);

$dispatcher = new Dispatcher($driver);

$counter = 1;
while (true) {
    $dispatcher->emit(new Message('type1', ['message' => $counter]));
    echo "Emited message $counter\n";
    $counter++;
    sleep(1);
}
