---
title: "API reference"
description: "Every public class, interface, trait, and exception in Hermes 5.x with its signature."
---

# API reference

Public types in `src/` for 5.x. Paths are relative to the package root. Examples elsewhere in this guide are the place for full scripts.

<Diagram name="api-reference-1" alt="Message implements MessageInterface, Emitter sends through DriverInterface, and Dispatcher calls HandlerInterface" />

## Tomaj\Hermes\Message

`src/Message.php`. Implements `MessageInterface`.

```php
public function __construct(
    string $type,
    ?array $payload = null,
    ?string $messageId = null,
    ?float $created = null,
    ?float $executeAt = null,
    int $retries = 0
)
```

| Method | Returns |
| --- | --- |
| `getId()` | `string`. UUID v4 when the constructor id was `null` or `''`. |
| `getCreated()` | `float`. `microtime(true)` when omitted. |
| `getExecuteAt()` | `float\|null` |
| `getType()` | `string` |
| `getPayload()` | `array\|null` |
| `getRetries()` | `int` |

There is no setter and no priority property.

## Tomaj\Hermes\MessageInterface

`src/MessageInterface.php`. The six getters above.

## Tomaj\Hermes\Emitter

`src/Emitter.php`. Implements `EmitterInterface`.

```php
public function __construct(DriverInterface $driver, ?LoggerInterface $logger = null)

public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
```

Calls `Driver::send`. Logs at `info` when `$logger` is set. Returns `$this`. Throws `UnknownPriorityException` when the driver does.

## Tomaj\Hermes\EmitterInterface

`src/EmitterInterface.php`. The `emit()` method above.

## Tomaj\Hermes\Dispatcher

`src/Dispatcher.php`. Implements `DispatcherInterface`.

```php
public const int DEFAULT_PRIORITY = 100;

public function __construct(
    DriverInterface $driver,
    ?LoggerInterface $logger = null,
    ?ShutdownInterface $shutdown = null,
    ?Randomizer $randomizer = null
)
```

When `$shutdown` is not `null` and the driver has `setShutdown()`, the constructor calls it. `$randomizer` (`Random\Randomizer`) supplies the retry jitter; it defaults to `new Randomizer()`. See [Retry](/guide/retry#reproducible-retry-times).

| Method | Behavior |
| --- | --- |
| `emit(MessageInterface $message, int $priority = self::DEFAULT_PRIORITY): DispatcherInterface` | Deprecated. Sends through the driver and logs. Prefer `Emitter`. |
| `handle(array $priorities = []): void` | Blocks in `Driver::wait`. Catches `ShutdownException` and logs a notice. Catches other `Exception`s and, if Tracy is enabled, logs them. |
| `registerHandler(string $type, HandlerInterface $handler): DispatcherInterface` | Appends one handler for `$type`. |
| `registerHandlers(string $type, array $handlers): DispatcherInterface` | Calls `registerHandler` for each. |
| `unregisterHandler(string $type, HandlerInterface $handler): DispatcherInterface` | Drops that instance for that type. |
| `unregisterAllHandlers(): DispatcherInterface` | Clears the map. |

`handle()`'s array is a list of priority integers. Empty means all queues the driver has.

Handler exceptions are caught per handler. They do not leave `handle()` unless the driver itself throws. Retry is described in [Retry](/guide/retry). The private delay formula is `microtime(true) + $retries ** 4 + 15 + $randomizer->getInt(1, 30) * ($retries + 1)`.

## Tomaj\Hermes\DispatcherInterface

`src/DispatcherInterface.php`. `registerHandler`, `registerHandlers`, `unregisterAllHandlers`, `unregisterHandler`, `handle`. `emit()` is only on the concrete class.

## Tomaj\Hermes\Driver\DriverInterface

`src/Driver/DriverInterface.php`.

```php
public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool;

public function setupPriorityQueue(string $name, int $priority): void;

public function wait(Closure $callback, array $priorities): void;
```

`send()` may throw `SerializeException` or `UnknownPriorityException`. `setupPriorityQueue()` may throw `NotSupportedException`. `wait()` may throw `UnknownPriorityException` or `ShutdownException`.

## Drivers

| Class | File | Constructor |
| --- | --- | --- |
| `RedisSetDriver` | `src/Driver/RedisSetDriver.php` | `(Redis $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')` |
| `PredisSetDriver` | `src/Driver/PredisSetDriver.php` | `(Client $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')` |
| `LazyRabbitMqDriver` | `src/Driver/LazyRabbitMqDriver.php` | `(AbstractConnection $connection, string $queue, array $amqpMessageProperties = [], int $refreshInterval = 0, string $consumerTag = 'hermes', bool $durable = true)` |
| `AmazonSqsDriver` | `src/Driver/AmazonSqsDriver.php` | `(SqsClient $client, string $queueName, array $queueAttributes = [])` |

`RedisSetDriver::setupPriorityQueue` and `PredisSetDriver::setupPriorityQueue` store `$name` at `$priority`. The other two always throw `NotSupportedException` and declare the `never` return type.

All four `use` `MaxItemsTrait`, `ShutdownTrait`, and `SerializerAwareTrait`.

## Traits

`src/Driver/MaxItemsTrait.php`

| Method | Behavior |
| --- | --- |
| `setMaxProcessItems(int $count): void` | `0` means no limit. |
| `incrementProcessedItems(): int` | Increments and returns the count. |
| `processed(): int` | Current count, starting at 0. |
| `shouldProcessNext(): bool` | `false` once `processed >= max` and max is not 0. |

`src/Driver/ShutdownTrait.php`

| Method | Behavior |
| --- | --- |
| `setShutdown(ShutdownInterface $shutdown): void` | Stores it and a new `DateTime` start. |

`shouldShutdown()` and `checkShutdown()` are private. `checkShutdown()` throws `ShutdownException` with an empty message.

`src/Driver/SerializerAwareTrait.php`

| Method | Behavior |
| --- | --- |
| `setSerializer(SerializerInterface $serializer): void` | Replaces the serializer. |

## Exceptions

| Class | Extends | When |
| --- | --- | --- |
| `HermesException` | `\Exception` | Base type. |
| `SerializeException` | `HermesException` | JSON encode or decode failed. |
| `Shutdown\ShutdownException` | `HermesException` | A shutdown check asked the worker to stop. |
| `Driver\UnknownPriorityException` | `HermesException` | `send()` or `wait()` used an integer that `setupPriorityQueue()` never stored. Message: `Unknown priority <n>`. |
| `Driver\NotSupportedException` | `HermesException` | Priority queues on RabbitMQ or SQS. |

## Tomaj\Hermes\SerializerInterface

`src/SerializerInterface.php`.

```php
public function serialize(MessageInterface $message): string;

public function unserialize(string $string): MessageInterface;
```

Both may throw `SerializeException`.

## Tomaj\Hermes\MessageSerializer

`src/MessageSerializer.php`. Default serializer constructed by every built-in driver.

`serialize()` JSON-encodes `message.id`, `message.type`, `message.created`, `message.payload`, `message.execute_at`, and `message.retries`, with `JSON_INVALID_UTF8_IGNORE`.

`unserialize()` requires an array with a `message` object that has `type`, `id`, and `created`. `execute_at` is used when it is numeric. `retries` is used when it is numeric, otherwise `0`. `payload` is used when it is an array, otherwise `null`. The return value is a `Message`.

## Tomaj\Hermes\Handler\HandlerInterface

`src/Handler/HandlerInterface.php`.

```php
public function handle(MessageInterface $message): bool;
```

The docblock allows the method to throw `\Exception`.

## Tomaj\Hermes\Handler\RetryTrait

`src/Handler/RetryTrait.php`.

| Method | Returns |
| --- | --- |
| `canRetry(): bool` | `true`. `Dispatcher` does not call this method. It only checks that the method exists. |
| `maxRetry(): int` | `25`. `Dispatcher` calls this. A new attempt is sent when `getRetries() < maxRetry()`. |

## Tomaj\Hermes\Handler\EchoHandler

`src/Handler/EchoHandler.php`. Uses `RetryTrait`.

`handle()` echoes `Received message: #<id> (type <type>)` and `Payload: <json>`, then returns `true`.

## Shutdown

`src/Shutdown/ShutdownInterface.php`

```php
public function shouldShutdown(DateTime $startTime): bool;

public function shutdown(?DateTime $shutdownTime = null): bool;
```

| Class | Constructor | Signal |
| --- | --- | --- |
| `SharedFileShutdown` | `(string $filePath)` | Writes the time with microseconds; `touch` also works. Stops when the time is later than start and not in the future. |
| `RedisShutdown` | `(Redis $redis, string $key = 'hermes_shutdown')` | `SET`s the time with microseconds. Stops when the value is set, not in the future, and later than start. |
| `PredisShutdown` | `(Client $redis, string $key = 'hermes_shutdown')` | Same rules. `shutdown()` is true when the Predis payload is `OK`. |

Details and the differences between the file and Redis rules are on [Shutdown and max items](/guide/shutdown).

## FAQ

### Where is the priority argument?

On `Emitter::emit()` and `DriverInterface::send()`, as the second argument. `Message` has no priority.

### Which methods can throw `UnknownPriorityException`?

`send()` and `wait()` on the Redis drivers, when a priority was not registered with `setupPriorityQueue()`.

### Is `Dispatcher::emit()` still supported?

It works but is deprecated. Use `Emitter`.

### Which traits do the built-in drivers use?

`MaxItemsTrait`, `ShutdownTrait`, and `SerializerAwareTrait`, on all four.

### Where are complete usage examples?

In the [Examples](/examples/) section and in `examples/` in the repository.
