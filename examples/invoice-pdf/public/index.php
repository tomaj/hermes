<?php

declare(strict_types=1);

use App\Hermes;
use Tomaj\Hermes\Message;

require __DIR__ . '/../vendor/autoload.php';

// POST /?order=1042 after the payment is confirmed.
$orderId = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT);
if (!is_int($orderId)) {
    http_response_code(422);
    echo 'Missing order id.';
    return;
}

Hermes::emitter()->emit(new Message('invoice.generate', ['order_id' => $orderId]));

http_response_code(202);
echo "Invoice for order {$orderId} is being generated.";
