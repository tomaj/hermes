<?php
declare(strict_types=1);


use PhpAmqpLib\Connection\AMQPLazyConnection;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require_once __DIR__.'/../../vendor/autoload.php';

$queueName = 'hermes_queue';
$connection = new AMQPLazyConnection('localhost', '5672', 'guest', 'guest', '/');
$driver = new LazyRabbitMqDriver($connection, $queueName);

$emitter = new Emitter($driver);

$counter = 1;
while (true) {
    $emitter->emit(new Message('type1', ['message' => $counter]));
    echo "Emited message $counter\n";
    $counter++;
    sleep(1);
}
