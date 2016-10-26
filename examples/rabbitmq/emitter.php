<?php

use Tomaj\Hermes\Driver\RabbitMqDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;
use PhpAmqpLib\Connection\AMQPStreamConnection;

require_once __DIR__.'/../../vendor/autoload.php';


$queueName = 'hermes_queue';
$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest', '/');
$channel = $connection->channel();
$channel->queue_declare($queueName, false, false, false, false);
$driver = new RabbitMqDriver($channel, $queueName);

$emitter = new Emitter($driver);

$counter = 1;
while (true) {
    $emitter->emit(new Message('type1', ['message' => $counter]));
    echo "Emited message $counter\n";
    $counter++;
    sleep(1);
}
