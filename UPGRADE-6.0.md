# Upgrading from Hermes 5.x to 6.0

This file lists the changes in 6.0 that may need work in your application, and the design decisions behind them. The full list of changes is in [CHANGELOG.md](CHANGELOG.md).

## Requirements

- PHP 8.4 or newer.
- `predis/predis` `^2.3 || ^3.0` if you use `PredisSetDriver` or `PredisShutdown`. Predis is only a suggested package, so Composer does not enforce this: upgrade it yourself.
- `ramsey/uuid` `^4.7` and `tracy/tracy` `^2.10.10`. Composer resolves these unless your project pins older versions.
- `psr/log` `^2 || ^3`. psr/log 1.x (no native types) is no longer allowed; a project that pins `psr/log` 1.x must upgrade it.
- `ext-json` is no longer listed in `require`: it is always enabled since PHP 8.0.

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

## Retry jitter no longer uses `rand()`

`Dispatcher` takes the 1 to 30 second retry jitter from a `Random\Randomizer` instead of the global `rand()` ([#82](https://github.com/tomaj/hermes/issues/82)). The formula is unchanged. `mt_srand()` no longer makes retry times reproducible. Pass a seeded `Randomizer` as the fourth argument instead, in tests only:

```php
use Random\Engine\Mt19937;
use Random\Randomizer;

$dispatcher = new Dispatcher($driver, $logger, null, new Randomizer(new Mt19937(42)));
```

If your container autowires `Dispatcher` and has a `Randomizer` service, that service is now injected. See [Retry](docs/guide/retry.md#reproducible-retry-times).

## Retries follow `RetryableHandlerInterface` and `canRetry()`

5.x retried a failed handler when it had methods named `canRetry()` and `maxRetry()` (`method_exists`), and never called `canRetry()`. 6.0 adds `Tomaj\Hermes\Handler\RetryableHandlerInterface` ([#107](https://github.com/tomaj/hermes/issues/107)):

```php
public function canRetry(MessageInterface $message, Throwable $exception): bool;

public function maxRetry(): int;
```

A retry copy is sent when the handler implements the interface or uses `RetryTrait`, the message has fewer than `maxRetry()` retries, and `canRetry()` returns `true`.

- **Handlers that use `RetryTrait`** keep retrying without changes. Adding `implements RetryableHandlerInterface` is recommended. `RetryTrait::canRetry()` now takes the message and the exception; it still returns `true`.
- **Handlers that define `canRetry()` and `maxRetry()` without the trait** are no longer retried. Add `implements RetryableHandlerInterface` and change `canRetry()` to the signature above.
- **A `canRetry()` that returns `false`** now really skips the retry. 5.x retried anyway; check that your overrides return what you want.
- A trait-based handler may keep a `canRetry(): bool` override without parameters, but once the class implements the interface it needs the full signature.

```php
use InvalidArgumentException;
use Throwable;
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

final class SendInvoiceHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function handle(MessageInterface $message): bool { /* ... */ }

    public function canRetry(MessageInterface $message, Throwable $exception): bool
    {
        return !$exception instanceof InvalidArgumentException; // a bad payload will not get better
    }
}
```

## Shutdown times keep microseconds

`SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` used to compare whole seconds, and an equal second counted as a shutdown request. A worker that the process manager started in the same second as `hermes:restart` (or `shutdown()`) therefore stopped again at once, and Supervisor could hit `startretries` ([#115](https://github.com/tomaj/hermes/issues/115)). Now:

- `shutdown()` stores the time with microseconds: the Redis value and the content of the file are `1700000000.123456`. `SharedFileShutdown` writes it into the file in place and still sets the file's modification time to that second, so the file keeps its owner and mode and the directory does not have to be writable. Unlike 5.x, which only changed the modification time, the process that calls `shutdown()` needs write access to the file. A later `touch` still counts.
- `shouldShutdown()` returns true only when the stored time is later than the worker's start time, not when it is equal. A worker that started after the request keeps running, even in the same second.
- A whole-second value counts as the start of that second. This covers a value written by Hermes 5.x and a file you `touch`. A worker that started earlier in that same second keeps running, while 5.x stopped it.
- `SharedFileShutdown` ignores a time in the future, like the Redis shutdowns already did. In 5.x a future modification time stopped every worker, including newly started ones, until that time passed.

During a rolling deploy, a 5.x worker still stops on a 6.0 request: it reads `1700000000.123456` as `1700000000`. Custom `ShutdownInterface` implementations are not affected.

## Classes are final

Every concrete class in `src/` is `final` ([#81](https://github.com/tomaj/hermes/issues/81)): `Message`, `MessageSerializer`, `Emitter`, `Dispatcher`, `EchoHandler`, the four drivers, the three shutdowns, and the exceptions `SerializeException`, `ShutdownException`, `NotSupportedException` and `UnknownPriorityException`. `HermesException` stays open, because the other exceptions extend it, and so can yours.

If you extended one of these classes, switch to the interface it implements and wrap the built-in class:

| You extended | Implement instead |
| --- | --- |
| `Message` | `MessageInterface` |
| `MessageSerializer` | `SerializerInterface` |
| `Emitter` | `EmitterInterface` |
| `Dispatcher` | `DispatcherInterface` |
| a driver | `DriverInterface` (the traits in `src/Driver` are still available) |
| a shutdown | `ShutdownInterface` |
| `EchoHandler` | `HandlerInterface`, or `RetryableHandlerInterface` with `RetryTrait` if you need retries |

For example, a driver that adds logging around `RedisSetDriver`:

```php
use Psr\Log\LoggerInterface;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\Shutdown\ShutdownInterface;

final class LoggingDriver implements DriverInterface
{
    public function __construct(
        private readonly RedisSetDriver $inner,
        private readonly LoggerInterface $logger,
    ) {}

    #[\Override]
    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        $this->logger->debug('send', ['id' => $message->getId()]);
        return $this->inner->send($message, $priority);
    }

    #[\Override]
    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->inner->setupPriorityQueue($name, $priority);
    }

    #[\Override]
    public function wait(\Closure $callback, array $priorities = []): void
    {
        $this->inner->wait($callback, $priorities);
    }

    public function setShutdown(ShutdownInterface $shutdown): void
    {
        $this->inner->setShutdown($shutdown);
    }
}
```

Forward `setShutdown()` as above. `Dispatcher` passes its `ShutdownInterface` only to a driver that has this method. Without it, a worker waiting on an empty queue does not notice a shutdown request; it stops only after the next message. Forward `setMaxProcessItems()` and `setSerializer()` the same way if you call them on the wrapper. `$inner` is typed as the concrete driver because these methods are not part of `DriverInterface`.

Doubling a final class fails in PHPUnit (`ClassIsFinalException`), Mockery and Prophecy. Mock the interface instead (`$this->createMock(EmitterInterface::class)`), and type-hint `EmitterInterface` and `DispatcherInterface` in your own classes so they accept the mock. Until you have changed those type hints, [`dg/bypass-finals`](https://github.com/dg/bypass-finals) can remove `final` in your test bootstrap. Use it in tests only.

All methods that implement an interface method carry `#[\Override]`, and PHPStan (`checkMissingOverrideMethodAttribute`) enforces it in `src/` and `tests/`.

## `setupPriorityQueue()` on RabbitMQ and SQS returns `never`

`LazyRabbitMqDriver::setupPriorityQueue()` and `AmazonSqsDriver::setupPriorityQueue()` always threw `NotSupportedException`. They now declare it with the `never` return type ([#83](https://github.com/tomaj/hermes/issues/83)). Runtime behavior is the same. If your code calls the method on one of these concrete types, PHPStan reports the code after the call as unreachable. Remove the call, or call it through `DriverInterface`.

## Private state is readonly

Constructor arguments are promoted to `readonly` properties in `Message`, `Dispatcher`, `Emitter`, the drivers and the shutdowns ([#78](https://github.com/tomaj/hermes/issues/78)). The public API is unchanged, but:

- Code that changes these private properties through reflection or `Closure::bind()` after construction gets an `Error`.
- PHP `serialize()` and `var_export()` list the `Message` properties in a different order. A `Message` serialized by 5.x still unserializes. `MessageSerializer` (JSON) output is unchanged.
- `Message`, `Emitter`, `SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` are declared `final readonly class` ([#83](https://github.com/tomaj/hermes/issues/83)). Setting an undeclared property on them (`$message->foo = 1`) throws `Error: Cannot create dynamic property` instead of a deprecation. Test doubles still work: PHPUnit mocks of the interfaces and `dg/bypass-finals` doubles with PHPUnit or Mockery were checked.

## Priorities stay plain integers

`Dispatcher::DEFAULT_PRIORITY` is now a typed class constant:

```php
public const int DEFAULT_PRIORITY = 100;
```

The value is the same.

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

## `Emitter` logs "Emitter send message"

`Emitter::emit()` logs `Emitter send message #<id> to driver <class>` instead of `Dispatcher send message #<id> to driver <class>` ([#111](https://github.com/tomaj/hermes/issues/111)). The level (`info`) and the context are unchanged. Update log searches and alerts that match the old text. The deprecated `Dispatcher::emit()` still logs `Dispatcher send message #<id> with priority <n> to driver <class>`.
