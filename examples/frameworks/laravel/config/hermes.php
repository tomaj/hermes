<?php

declare(strict_types=1);

return [
    // redis (ext-redis), predis, rabbitmq or sqs
    'driver' => env('HERMES_DRIVER', 'redis'),
    'queue' => env('HERMES_QUEUE', 'hermes'),
    'max_items' => (int) env('HERMES_MAX_ITEMS', 1000),
    'shutdown_file' => storage_path('framework/hermes-shutdown'),

    'redis' => [
        'host' => env('HERMES_REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('HERMES_REDIS_PORT', 6379),
    ],
    'rabbitmq' => [
        'host' => env('HERMES_RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('HERMES_RABBITMQ_PORT', 5672),
        'user' => env('HERMES_RABBITMQ_USER', 'guest'),
        'password' => env('HERMES_RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('HERMES_RABBITMQ_VHOST', '/'),
    ],
    'sqs' => [
        'region' => env('HERMES_SQS_REGION', 'eu-central-1'),
        'endpoint' => env('HERMES_SQS_ENDPOINT'),
        'key' => env('AWS_ACCESS_KEY_ID', ''),
        'secret' => env('AWS_SECRET_ACCESS_KEY', ''),
    ],
];
