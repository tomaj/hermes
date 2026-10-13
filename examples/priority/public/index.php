<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

$emitter = new Emitter(Hermes::driver());

// A password reset is waiting for a person: high.
$emitter->emit(new Message('email.password_reset', ['email' => 'jane@example.com']), Hermes::HIGH);

// A receipt can take a few seconds: normal.
$emitter->emit(new Message('email.receipt', ['order_id' => 1042]), Hermes::NORMAL);

// The weekly newsletter can wait for an idle worker: low.
foreach (range(1, 3) as $i) {
    $emitter->emit(new Message('email.newsletter', ['subscriber_id' => $i]), Hermes::LOW);
}

echo "Queued 5 messages.\n";
