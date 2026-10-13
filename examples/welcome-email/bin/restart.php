<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Shutdown\PredisShutdown;

require __DIR__ . '/../vendor/autoload.php';

// Workers started before now exit after their current message.
$shutdown = new PredisShutdown(Hermes::redis());
$shutdown->shutdown();
echo "Shutdown signal sent.\n";
