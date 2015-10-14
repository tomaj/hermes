<?php

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Tomaj\Hermes\Message;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;


$driver = new RedisSetDriver();
$dispatcher = new Dispatcher($driver);

$message = new Message('myevent', ['a' => 'b', 'asdsad' => 'asdsad']);

$dispatcher->emit($message);