<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

// A customer reserved a table. Remind them one day before, or in REMIND_IN seconds while testing.
$reservation = ['id' => 77, 'email' => 'jane@example.com', 'at' => strtotime('+2 days 19:00')];
$remindAt = (float) (getenv('REMIND_IN') ? time() + (int) getenv('REMIND_IN') : $reservation['at'] - 86400);

Hermes::emitter()->emit(new Message(
    'reservation.remind',
    ['reservation_id' => $reservation['id'], 'email' => $reservation['email']],
    null,      // message id, generated
    null,      // created, now
    $remindAt, // executeAt: the worker will not run it before this time
));

echo 'Reminder scheduled for ' . date('Y-m-d H:i:s', (int) $remindAt) . "\n";
