---
title: "API reference"
description: "Every public class, interface, trait, and exception in Hermes 6.x with its signature."
---

# API reference

Public types in `src/` for 6.x. Paths are relative to the package root. Examples elsewhere in this guide are the place for full scripts.

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
| `getId()` | `string`. UUID v4 when the constructor id was `null` (the default) or `''`; any other string, including `"0"`, is returned as given. |
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
| `emit(MessageInterface $message, int $priority = self::DEFAULT_PRIORITY): DispatcherInterface` | Deprecated since 2.0, removed in 7.0. Sends through the driver and logs; the first call on each instance also triggers `E_USER_DEPRECATED` and logs a `warning`. Use `Emitter`. |
| `handle(array $priorities = []): void` | Blocks in `Driver::wait`. Catches `ShutdownException` and logs a notice. Catches other `Exception`s, logs them at `error`, writes them to Tracy when Tracy is installed and enabled, and rethrows them. |
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

`send()` may throw `SerializeException` or `UnknownPriorityException`. `setupPriorityQueue()` may throw `NotSupportedException`. `wait()` may throw `UnknownPriorityException`, `SerializeException` (a body that cannot be unserialized) or `ShutdownException`.

## Drivers

| Class | File | Constructor |
| --- | --- | --- |
| `RedisSetDriver` | `src/Driver/RedisSetDriver.php` | `(Redis $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')` |
| `PredisSetDriver` | `src/Driver/PredisSetDriver.php` | `(Client $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')` |
| `LazyRabbitMqDriver` | `src/Driver/LazyRabbitMqDriver.php` | `(AbstractConnection $connection, string $queue, array $amqpMessageProperties = [], int $refreshInterval = 0, string $consumerTag = 'hermes', bool $durable = true)` |
| `AmazonSqsDriver` | `src/Driver/AmazonSqsDriver.php` | `(SqsClient $client, string $queueName, array $queueAttributes = [])` |
| `FileSystemDriver` | `src/Driver/FileSystemDriver.php` | `(string $directory, string $queue = 'hermes', int $refreshInterval = 1, int $visibilityTimeout = 300)` |
| `KafkaDriver` | `src/Driver/KafkaDriver.php` | `(Producer $producer, ?KafkaConsumer $consumer = null, string $topic = 'hermes', int $consumeTimeout = 1000, int $flushTimeout = 10000)` |

`RedisSetDriver::setupPriorityQueue`, `PredisSetDriver::setupPriorityQueue` and `FileSystemDriver::setupPriorityQueue` store `$name` at `$priority`; `FileSystemDriver` throws `InvalidArgumentException` for a name that is not a safe folder name. `LazyRabbitMqDriver`, `AmazonSqsDriver` and `KafkaDriver` always throw `NotSupportedException` and declare the `never` return type.

`RedisSetDriver`, `PredisSetDriver` and `FileSystemDriver` throw `InvalidArgumentException` from the constructor for a negative `$refreshInterval`, and `FileSystemDriver` also for a negative `$visibilityTimeout`.

All six `use` `MaxItemsTrait`, `ShutdownTrait`, and `SerializerAwareTrait`.

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
| `setShutdown(ShutdownInterface $shutdown): void` | Stores it and a new `DateTimeImmutable` start. |

`shouldShutdown()` and `checkShutdown()` are private. `checkShutdown()` throws `ShutdownException` with an empty message.

`src/Driver/SerializerAwareTrait.php`

| Method | Behavior |
| --- | --- |
| `setSerializer(SerializerInterface $serializer): void` | Replaces the serializer. |

## Exceptions

| Class | Extends | When |
| --- | --- | --- |
| `HermesException` | `\Exception` | Base type. |
| `SerializeException` | `HermesException` | JSON encode or decode failed. The message never contains the serialized string, only the reason, its length and a SHA-256 prefix. |
| `Shutdown\ShutdownException` | `HermesException` | A shutdown check asked the worker to stop. |
| `Driver\UnknownPriorityException` | `HermesException` | `send()` or `wait()` used an integer that `setupPriorityQueue()` never stored. Message: `Unknown priority <n>`. |
| `Driver\NotSupportedException` | `HermesException` | Priority queues on RabbitMQ, SQS or Kafka. |
| `Driver\FileSystemDriverException` | `HermesException` | `FileSystemDriver` cannot create a folder, or write, move or read a message file. |
| `Driver\KafkaDriverException` | `HermesException` | Kafka did not confirm a sent message, the consumer reported an error, or `wait()` has no consumer. |

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

`unserialize()` requires an array with a `message` object that has `type`, `id`, and `created`. `execute_at` is used when it is numeric. `retries` is used when it is numeric, otherwise `0`. `payload` is used when it is an array, otherwise `null`. The return value is a `Message`. Otherwise it throws `SerializeException` with the reason, the length and `substr(hash('sha256', $string), 0, 16)`: `Cannot unserialize message: invalid JSON (<error>)`, `Cannot unserialize message: no "message" key`, `Invalid message format: "message" needs type, id and created` or `Invalid message format: type and id must be strings and created a number`. Invalid JSON keeps the `JsonException` as `previous`.

## Tomaj\Hermes\Handler\HandlerInterface

`src/Handler/HandlerInterface.php`.

```php
public function handle(MessageInterface $message): bool;
```

The docblock allows the method to throw `\Exception`.

## Tomaj\Hermes\Handler\RetryableHandlerInterface

`src/Handler/RetryableHandlerInterface.php`. Extends `HandlerInterface`.

| Method | Meaning |
| --- | --- |
| `canRetry(MessageInterface $message, Throwable $exception): bool` | Called after `handle()` threw, while `getRetries() < maxRetry()`. `false` skips the retry. |
| `maxRetry(): int` | The most retries a message gets. `0` turns retries off. |

## Tomaj\Hermes\Handler\RetryTrait

`src/Handler/RetryTrait.php`. Implements the methods of `RetryableHandlerInterface`; the class declares the interface. A handler that uses the trait, or defines both methods itself, without declaring the interface is retried by the deprecated 5.x rule: while `getRetries() < maxRetry()`, without calling `canRetry()`, with one `E_USER_DEPRECATED` and one `warning` per class. 7.0 removes that rule.

| Method | Returns |
| --- | --- |
| `canRetry(MessageInterface $message, Throwable $exception): bool` | `true`. |
| `maxRetry(): int` | `25`. A new attempt is sent when `getRetries() < maxRetry()`. |

## Tomaj\Hermes\Handler\EchoHandler

`src/Handler/EchoHandler.php`. Implements `RetryableHandlerInterface` and uses `RetryTrait`.

`handle()` echoes `Received message: #<id> (type <type>)` and `Payload: <json>`, then returns `true`.

## Shutdown

`src/Shutdown/ShutdownInterface.php`

```php
public function shouldShutdown(DateTimeInterface $startTime): bool;

public function shutdown(?DateTimeInterface $shutdownTime = null): bool;
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

It works, but it is deprecated since 2.0 and removed in 7.0. The first call on each `Dispatcher` triggers `E_USER_DEPRECATED` and logs a `warning`. Use `Emitter`.

### Which traits do the built-in drivers use?

`MaxItemsTrait`, `ShutdownTrait`, and `SerializerAwareTrait`, on all six.

### Where are complete usage examples?

In the [Examples](/examples/) section and in `examples/` in the repository.
