<?php

declare(strict_types=1);

// A fake receiver for local testing: fails the first attempt with 503, accepts the next one.
$attempt = (int) ($_SERVER['HTTP_WEBHOOK_ATTEMPT'] ?? 1);
$expected = hash_hmac('sha256', $_SERVER['HTTP_WEBHOOK_TIMESTAMP'] . '.' . file_get_contents('php://input'), 'whsec_change_me');
$valid = hash_equals($expected, $_SERVER['HTTP_WEBHOOK_SIGNATURE'] ?? '');

error_log(sprintf('webhook %s attempt %d signature %s', $_SERVER['HTTP_WEBHOOK_ID'] ?? '?', $attempt, $valid ? 'ok' : 'BAD'));
http_response_code($attempt === 1 ? 503 : 204);
