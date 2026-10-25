<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

require __DIR__ . '/../vendor/autoload.php';

@mkdir(Hermes::directory(), 0o775, true);
new SharedFileShutdown(Hermes::directory() . '/shutdown')->shutdown();
echo "workers asked to stop\n";
