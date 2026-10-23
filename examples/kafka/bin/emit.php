<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

// php bin/emit.php <order number>
$order = $argv[1] ?? '1001';

// emit() returns after Kafka confirmed the message
new Emitter(Hermes::emitterDriver())->emit(new Message('order.placed', ['order' => $order]));

echo "placed order {$order}\n";
