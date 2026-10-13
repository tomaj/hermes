<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

// Something happened in your app, for example an order was shipped.
$event = [
    'event' => 'order.shipped',
    'order_id' => 1042,
    'shipped_at' => date(DATE_ATOM),
];

Hermes::emitter()->emit(new Message('webhook.deliver', [
    'url' => getenv('WEBHOOK_URL') ?: 'http://127.0.0.1:9000/',
    'secret' => 'whsec_change_me',
    'body' => $event,
]));

http_response_code(202);
echo "Webhook queued.\n";
