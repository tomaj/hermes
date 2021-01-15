<?php
declare(strict_types=1);

use Predis\Client;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require_once __DIR__.'/../../vendor/autoload.php';

$redis = new Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
]);
$driver = new PredisSetDriver($redis);
$driver->setupPriorityQueue('hermes_low', \Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', \Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY + 10);

$emitter = new Emitter($driver);

$counter = 1;
$priorities = [\Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY, \Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY - 10, \Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY + 10];
while (true) {
    $emitter->emit(new Message('type1', ['message' => $counter]), $priorities[rand(0, count($priorities) - 1)]);
    echo "Emited message $counter\n";
    $counter++;
    sleep(1);
}
