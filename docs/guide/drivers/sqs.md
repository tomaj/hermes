# Amazon SQS

`Tomaj\Hermes\Driver\AmazonSqsDriver` needs `aws/aws-sdk-php` 3.x, which is what `composer.json` suggests (`aws/aws-sdk-php: 3.*`). The class docblock still shows the AWS SDK v2 `SqsClient::factory()` and `Aws\Common\Aws` examples. The code and `examples/sqs` use an SDK v3 client.

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

There is no public sleep setting. `$sleepInterval` is a private `0` and nothing assigns it, so the empty-queue branch never sleeps. Waiting is `WaitTimeSeconds => 1` on `receiveMessage`.

`WaitTimeSeconds` is `1`. The constructor can pass `VisibilityTimeout` through to `createQueue`, as a string attribute. The worker never calls `ChangeMessageVisibility`. It deletes the message before `handle()`, so the visibility window does not cover handler runtime. Once `deleteMessage` succeeds, a crash inside the handler does not put that SQS message back.

<Diagram name="drivers-sqs-1" alt="Emit and handle diagram" />

## Receive and delete

<Diagram name="drivers-sqs-2" alt="Receive and delete diagram" />

`wait()` reads `$result['Messages']`. For each entry it deletes by `ReceiptHandle`, unserializes `Body`, and only then calls the dispatcher callback. `incrementProcessedItems()` runs after the callback.

If the handler throws, the SQS message is already deleted. [Retry](/guide/retry) can `sendMessage` a new body. That new body is visible immediately, because this driver does not honor `executeAt`.

`$priorities` is unused.

`SharedFileShutdown` works here. Pass it to the `Dispatcher` constructor. The driver uses `ShutdownTrait`, so the constructor forwards it and `wait()` checks it at the start of each loop.
