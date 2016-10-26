<?php

use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require_once __DIR__.'/../../vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$driver = new RedisSetDriver($redis);

$emitter = new Emitter($driver);

$counter = 1;
while (true) {
    $emitter->emit(new Message('type1', ['message' => $counter]));
    echo "Emited message $counter\n";
    $counter++;
    sleep(1);
}
