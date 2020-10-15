<?php
declare(strict_types=1);

use Tomaj\Hermes\Restart\RedisRestart;

require_once __DIR__.'/../../vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
(new RedisRestart($redis))->restart();
