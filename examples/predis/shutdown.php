<?php
declare(strict_types=1);

use Predis\Client;
use Tomaj\Hermes\Shutdown\PredisShutdown;
use Tomaj\Hermes\Driver\PredisSetDriver;

require_once __DIR__.'/../../vendor/autoload.php';

$redis = new Client([
    'scheme' => 'tcp',
    'host'   => '127.0.0.1',
    'port'   => 6379,
]);
$driver = new PredisSetDriver($redis);
(new PredisShutdown($redis))->shutdown();
