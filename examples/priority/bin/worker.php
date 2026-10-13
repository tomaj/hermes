<?php

declare(strict_types=1);

use App\Hermes;
use App\PrintHandler;
use Tomaj\Hermes\Dispatcher;

require __DIR__ . '/../vendor/autoload.php';

// php bin/worker.php          takes every priority, highest first
// php bin/worker.php high     takes only the high queue
$priorities = ($argv[1] ?? '') === 'high' ? [Hermes::HIGH] : [];

$dispatcher = new Dispatcher(Hermes::driver());
$handler = new PrintHandler();
foreach (['email.password_reset', 'email.receipt', 'email.newsletter'] as $type) {
    $dispatcher->registerHandler($type, $handler);
}
$dispatcher->handle($priorities);
