<?php

declare(strict_types=1);

namespace App;

use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

final class WebhookHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function __construct(private HttpClientInterface $http) {}

    #[\Override]
    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload() ?? [];
        $body = json_encode($payload['body'], JSON_THROW_ON_ERROR);
        $timestamp = (string) time();

        $response = $this->http->request('POST', $payload['url'], [
            'headers' => [
                'Content-Type' => 'application/json',
                // The message id stays the same on every retry. Receivers use it to drop duplicates.
                'Webhook-Id' => $message->getId(),
                'Webhook-Attempt' => (string) ($message->getRetries() + 1),
                'Webhook-Timestamp' => $timestamp,
                'Webhook-Signature' => hash_hmac('sha256', "{$timestamp}.{$body}", $payload['secret']),
            ],
            'body' => $body,
            'timeout' => 10,
        ]);

        $status = $response->getStatusCode(); // waits for the response
        if ($status >= 500 || $status === 429) {
            // Throwing makes Hermes send a copy with a later executeAt.
            throw new RuntimeException("Receiver answered {$status}");
        }

        // 2xx is delivered. Any other 4xx will not get better on a retry, so give up.
        return $status < 300;
    }

    public function maxRetry(): int
    {
        return 8; // last attempt roughly an hour and a half after the first one
    }
}
