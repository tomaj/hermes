<?php
declare(strict_types=1);

use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require_once __DIR__.'/../../vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);
$driver->setupPriorityQueue('hermes_low', \Tomaj\Hermes\Dispatcher::PRIORITY_LOW);
$driver->setupPriorityQueue('hermes_high', \Tomaj\Hermes\Dispatcher::PRIORITY_HIGH);

$emitter = new Emitter($driver);

$counter = 1;
$priorities = [\Tomaj\Hermes\Dispatcher::PRIORITY_MEDIUM, \Tomaj\Hermes\Dispatcher::PRIORITY_LOW, \Tomaj\Hermes\Dispatcher::PRIORITY_HIGH];
while (true) {
    $emitter->emit(new Message('type1', ['message' => $counter]), $priorities[rand(0, count($priorities) - 1)]);
    echo "Emited message $counter\n";
    $counter++;
    sleep(1);
}
