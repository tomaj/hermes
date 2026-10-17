---
title: "Amazon SQS driver"
description: "AmazonSqsDriver for Hermes: queue creation, receive and delete, IAM permissions, and what it does not support."
---

# Amazon SQS

`Tomaj\Hermes\Driver\AmazonSqsDriver` needs `aws/aws-sdk-php` 3.x. `composer.json` lists it under `suggest` ("Needed for AmazonSqsDriver"), and the tests run against `^3.337`. The class docblock still shows the AWS SDK v2 `SqsClient::factory()` and `Aws\Common\Aws` examples. The code and `examples/sqs` use an SDK v3 client.

```php
public function __construct(SqsClient $client, string $queueName, array $queueAttributes = [])
```

The constructor calls `createQueue` and stores `QueueUrl` from the result:

```php
$result = $client->createQueue([
    'QueueName' => $queueName,
    'Attributes' => $queueAttributes,
]);
$this->queueUrl = $result->get('QueueUrl');
```

The IAM principal used by both the emitter and the worker needs permission to create the queue, because both processes construct the driver. Attribute values are the strings SQS expects. The unit test passes `VisibilityTimeout` as `'60'`.

## Emit and handle

```php
use Aws\Sqs\SqsClient;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;

$client = new SqsClient([
    'version' => 'latest',
    'region' => 'eu-central-1',
    'credentials' => [
        'key' => 'your-key',
        'secret' => 'your-secret',
    ],
]);

$driver = new AmazonSqsDriver($client, 'hermes', [
    'VisibilityTimeout' => '60',
]);

$emitter = new Emitter($driver);
$emitter->emit(new Message('type1', ['message' => 1]));
```

```php
$driver = new AmazonSqsDriver($client, 'hermes');
$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`send()` calls `sendMessage` with `QueueUrl` and `MessageBody`. The priority argument is ignored. `setupPriorityQueue()` throws `NotSupportedException` (`AmazonSQS is not supporting priority queues now`). `executeAt` is not turned into an SQS delay. The body is published now, with `execute_at` only inside the JSON.

There is no sleep setting. On an empty queue the worker waits in `receiveMessage` (`WaitTimeSeconds => 1`, long polling), checks for shutdown, and polls again.

`WaitTimeSeconds` is `1`. The constructor can pass `VisibilityTimeout` through to `createQueue`, as a string attribute. The worker never calls `ChangeMessageVisibility`. It deletes the message only after the callback returns, so the visibility timeout has to be longer than your slowest handler. If the handler crashes the worker, or the callback throws, the message is not deleted and SQS delivers it again when the visibility timeout expires (or moves it to a dead-letter queue when you configure a redrive policy). Delivery is at least once, so handlers must be idempotent. Up to 5.x the driver deleted the message before the handler ([issue #69](https://github.com/tomaj/hermes/issues/69)).

<Diagram name="drivers-sqs-1" alt="Sequence: the worker creates the queue, receives a message, runs the handler, then deletes it" />

## Receive and delete

<Diagram name="drivers-sqs-2" alt="Sequence of the SQS receive loop: receiveMessage, unserialize and callback, then deleteMessage" />

`wait()` reads `$result['Messages']`. For each entry it unserializes `Body`, calls the dispatcher callback, and only then deletes by `ReceiptHandle`. `incrementProcessedItems()` runs after the delete. If unserializing fails, the message is not deleted either.

The dispatcher catches handler exceptions, so a handler that throws still counts as handled and its message is deleted. [Retry](/guide/retry) can `sendMessage` a new body. That new body is visible immediately, because this driver does not honor `executeAt`.

A PHP `Error` (for example `TypeError`) from a handler is not caught. The worker exits, the message stays in SQS, and it comes back after the visibility timeout ([#112](https://github.com/tomaj/hermes/issues/112)). A body that cannot be unserialized also comes back. Add a redrive policy so such messages end up in a dead-letter queue. Set it on an existing queue with `aws sqs set-queue-attributes`, not through the constructor: the constructor calls `CreateQueue`, which fails with `QueueAlreadyExists` when its attributes differ from the queue's.

`$priorities` is unused.

`SharedFileShutdown` works here. Pass it to the `Dispatcher` constructor. The driver uses `ShutdownTrait`, so the constructor forwards it and `wait()` checks it at the start of each loop.

## FAQ

### Which AWS SDK version do I need?

`aws/aws-sdk-php` 3.x.

### Why does the emitter need permission to create queues?

The constructor calls `createQueue` on every construction, in the web process and in the worker.

### Does the visibility timeout protect a slow handler?

Yes, from 6.0. The message is deleted only after the handler returns, so set `VisibilityTimeout` above your slowest handler. Otherwise SQS hands the message to another worker while the first one still runs it.

### Do delays or retries wait?

No. `executeAt` is not turned into an SQS delay, so messages and retries are visible at once.

### Does the worker sleep on an empty queue?

It waits with `WaitTimeSeconds` 1 on `receiveMessage`. There is no extra sleep setting.
