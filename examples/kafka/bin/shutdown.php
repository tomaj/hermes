<?php

declare(strict_types=1);

use Tomaj\Hermes\Shutdown\SharedFileShutdown;

require __DIR__ . '/../vendor/autoload.php';

$var = getenv('APP_VAR') ?: __DIR__ . '/../var';
@mkdir($var, 0o775, true);
new SharedFileShutdown("{$var}/shutdown")->shutdown();
echo "workers asked to stop\n";
