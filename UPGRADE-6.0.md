# Upgrading from Hermes 5.x to 6.0

This file lists the changes in 6.0 that may need work in your application, and the design decisions behind them. The full list of changes is in [CHANGELOG.md](CHANGELOG.md).

## Requirements

- PHP 8.4 or newer.
- `predis/predis` `^2.3 || ^3.0` if you use `PredisSetDriver` or `PredisShutdown`. Predis is only a suggested package, so Composer does not enforce this: upgrade it yourself.
- `ramsey/uuid` `^4.7` and `tracy/tracy` `^2.10.10`. Composer resolves these unless your project pins older versions.

## Deploy order and rollback

The message format is unchanged: 5.x and 6.0 read each other's messages. On Redis, Predis and SQS you can run 5.x and 6.0 producers and workers side by side during a rolling deploy, after the SQS step below.

RabbitMQ is the exception, because 6.0 declares the queue durable. Read the [RabbitMQ](#rabbitmq-durable-queue-and-acknowledgements) section before you deploy.

To roll back to 5.x, deploy 5.x again. A 5.x `RedisSetDriver` worker stops delivering the schedule set again. A 5.x process cannot declare a durable RabbitMQ queue, so a rollback needs the same drain and delete as the upgrade. If you need a quick rollback, deploy 6.0 with `durable: false` first.

## Driver behavior

### Redis: clear old entries from the schedule set before you deploy

`RedisSetDriver` delivers delayed messages and retries again ([#95](https://github.com/tomaj/hermes/issues/95)). 5.x never removed them from the schedule sorted set, so entries may have piled up there. The first 6.0 worker delivers every entry that is already due, all at once.

Before you deploy, inspect the set. The key is `hermes_schedule` unless you passed another `$scheduleKey` (the fourth constructor argument). Add the `-h`, `-p` and `-n` options of your connection, and prepend your key prefix (phpredis `Redis::OPT_PREFIX`) if you set one. Scores are Unix timestamps:

```bash
redis-cli ZCARD hermes_schedule                                  # all entries
redis-cli ZCOUNT hermes_schedule -inf "$(date +%s)"              # entries already due
redis-cli ZRANGEBYSCORE hermes_schedule -inf "$(date +%s)"       # their JSON bodies
redis-cli ZREMRANGEBYSCORE hermes_schedule -inf "$(date +%s)"    # drop all due entries
```

The due entries are delayed messages and retries that 5.x never delivered. Remove them only if they should not run any more. Remove single entries with `ZREM hermes_schedule '<body>'`. `PredisSetDriver` already removed due entries in 5.x and is not affected.

### Redis and Predis: priority order

`RedisSetDriver` and `PredisSetDriver` poll priority queues from the highest priority number down, not in reverse registration order ([#50](https://github.com/tomaj/hermes/issues/50)).

### RabbitMQ: durable queue and acknowledgements

`LazyRabbitMqDriver` declares a durable queue and publishes persistent messages. The emitter and the worker both declare the queue. For a queue that 5.x created non-durable, RabbitMQ rejects the declaration with `PRECONDITION_FAILED`, in 6.0 and also in 5.x once the queue is durable:

- `emit()` throws.
- The worker exits with status 0 and logs nothing, because `Dispatcher::handle()` catches the exception and only passes it to Tracy when Tracy is enabled. Your process manager restarts it in a loop.

Pick one way:

- **No downtime:** deploy 6.0 with `durable: false` on every emitter and worker ([RabbitMQ driver](docs/guide/drivers/rabbitmq.md#upgrading-a-5x-queue)). 5.x and 6.0 then share the queue.
- **Durable queue:** stop the producers, let the 5.x workers empty the queue, stop them, delete the queue and deploy 6.0. `--if-empty` refuses to delete a queue that still has messages:

```bash
rabbitmqctl list_queues -p / name durable messages
rabbitmqctl delete_queue -p / --if-empty hermes
```

Messages are acknowledged after the dispatcher callback returns, so a crashed worker gets the message again. A body that cannot be unserialized is rejected without requeue ([#70](https://github.com/tomaj/hermes/issues/70)). To keep such messages, add a dead-letter exchange with a policy:

```bash
rabbitmqctl set_policy -p / hermes-dlx '^hermes$' '{"dead-letter-exchange":"hermes-dlx"}' --apply-to queues
```

Replace `hermes` in the pattern with your queue name and `/` with your vhost. Declare the `hermes-dlx` exchange and bind a queue to it, or the rejected messages are dropped. Only one policy applies to a queue, the one with the highest priority, so merge this key into an existing policy instead of adding a second one.

Use a policy, not queue arguments. The driver declares the queue without arguments, so a queue declared with `x-dead-letter-exchange` or `x-queue-type` fails with `PRECONDITION_FAILED`.

### Amazon SQS: delete after handling

`AmazonSqsDriver` deletes a message only after it was handled ([#69](https://github.com/tomaj/hermes/issues/69)). Set `VisibilityTimeout` above your slowest handler, or SQS delivers the message again while it is still being handled.

Change attributes of an existing queue on the queue itself, not through the driver. The driver calls `CreateQueue` with its `$queueAttributes` on every start, in 5.x too, and SQS answers `QueueAlreadyExists` when they differ from the queue's current values. The constructor then throws, so neither the emitter nor the worker starts.

So first deploy code that passes no `$queueAttributes` to the driver. This works on 5.x and 6.0 for an existing queue. Then change the queue:

```bash
QUEUE_URL="$(aws sqs get-queue-url --queue-name hermes --query QueueUrl --output text)"
aws sqs set-queue-attributes --queue-url "$QUEUE_URL" --attributes '{"VisibilityTimeout":"60"}'
```

Create new queues (for example in development) with the same attributes, using `aws sqs create-queue --attributes`.

### At-least-once delivery and handler errors

RabbitMQ and SQS now deliver at least once, so their handlers should be idempotent. The Redis drivers still remove a message before the handler runs.

`Dispatcher` catches an `Exception` from a handler: the message counts as handled, is acknowledged (RabbitMQ) or deleted (SQS), and `RetryTrait` decides whether to retry. A PHP `Error`, such as a `TypeError`, is not caught ([#112](https://github.com/tomaj/hermes/issues/112)). It stops the worker before the acknowledgement, so RabbitMQ requeues the message and SQS delivers it again after the visibility timeout. A handler that always fails this way crashes every worker that receives the message.

Until #112 is fixed, convert errors in your handlers. The dispatcher then treats them like any other exception, including retries when the handler uses `RetryTrait`:

```php
public function handle(MessageInterface $message): bool
{
    try {
        // your work
    } catch (\Error $e) {
        throw new \RuntimeException($e->getMessage(), 0, $e);
    }
    return true;
}
```

On SQS, a redrive policy also stops the loop: after `maxReceiveCount` receives, SQS moves the message to the dead-letter queue. Set it on the queue, as above:

```bash
aws sqs set-queue-attributes --queue-url "$QUEUE_URL" --attributes \
  '{"RedrivePolicy":"{\"deadLetterTargetArn\":\"arn:aws:sqs:eu-central-1:123456789012:hermes-dlq\",\"maxReceiveCount\":\"5\"}"}'
```

As with `VisibilityTimeout`, do not pass `RedrivePolicy` to the driver.

RabbitMQ has no equivalent for this driver: delivery limits need quorum queues, which the driver cannot declare.

## Private state is readonly

Constructor arguments are promoted to `readonly` properties in `Message`, `Dispatcher`, `Emitter`, the drivers and the shutdowns ([#78](https://github.com/tomaj/hermes/issues/78)). The public API is unchanged, but:

- Code that changes these private properties through reflection or `Closure::bind()` after construction gets an `Error`.
- PHP `serialize()` and `var_export()` list the `Message` properties in a different order. A `Message` serialized by 5.x still unserializes. `MessageSerializer` (JSON) output is unchanged.

## Priorities stay plain integers

`Dispatcher::DEFAULT_PRIORITY` is now a typed class constant:

```php
public const int DEFAULT_PRIORITY = 100;
```

The value is the same. If you extend `Dispatcher` and redeclare `DEFAULT_PRIORITY`, declare it as `public const int` too. An untyped redeclaration is now a fatal error: `Type of …::DEFAULT_PRIORITY must be compatible with Tomaj\Hermes\Dispatcher::DEFAULT_PRIORITY of type int`.

**Decision: no `Priority` enum or value object.** Priorities stay `int` in `DriverInterface::send()`, `setupPriorityQueue()`, `Emitter::emit()` and `Dispatcher::handle()` ([#80](https://github.com/tomaj/hermes/issues/80)), because:

- A backed enum is a closed set. Hermes priorities are open: every application registers its own integers (`110`, `150`, `200`, …) with `setupPriorityQueue()`, so the library cannot list the cases.
- The integer is the ordering key. The Redis drivers poll from the highest number down ([#50](https://github.com/tomaj/hermes/issues/50)). A value object would only wrap that integer.
- Changing the parameter type of the driver interface would break every custom driver and every call site, with no new safety. An unknown priority still has to be checked at runtime, because it depends on what was registered.

The runtime check stays: on the Redis and Predis drivers, sending with a priority that was not registered throws `UnknownPriorityException`. The RabbitMQ and SQS drivers have no priority queues and ignore the priority argument of `send()`. To name your priorities, use your own typed constants:

```php
final class Priority
{
    public const int LOW = 90;
    public const int HIGH = 110;
}

$driver->setupPriorityQueue('hermes_high', Priority::HIGH);
$emitter->emit($message, Priority::HIGH);
```

Log levels are not wrapped either. Hermes passes the PSR-3 `Psr\Log\LogLevel` string constants straight to your logger.
