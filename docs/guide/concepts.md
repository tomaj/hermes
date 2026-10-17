---
title: "Concepts"
description: "Message, Emitter, Dispatcher, Handler, and Driver: the five types of Hermes and how a message moves between them."
---

# Concepts

Five types do the work. Everything else is a driver, a shutdown signal, or a serializer.

<Diagram name="concepts-1" alt="A web request creates a Message, the Emitter passes it to the Driver and queue, and the Dispatcher hands it to the Handler" />

A message is not a row with a status column. These names are the stages the code actually passes through. `Processing` starts only after the driver has already removed the payload from the broker.

<Diagram name="concepts-2" alt="Message states: emitted, scheduled or queued, processing, then done, failed, or retried as a copy" />

## Message

`Tomaj\Hermes\Message` is the built-in `MessageInterface`. The constructor arguments are type, payload, id, created time, execute-at time, and retry count:

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

Priority is not one of them. An empty id becomes a UUID v4 from `ramsey/uuid`. `created` defaults to `microtime(true)`. `executeAt` stays `null` unless you pass a timestamp. `retries` starts at `0`.

`getPayload()` is `array|null`. The payload has to survive JSON. Do not put resources, sockets, or a live database connection in it. `MessageSerializer` stores this shape:

```json
{
  "message": {
    "id": "6f1c0a2e-9b44-4d1a-8c33-0a9e5d77b214",
    "type": "send-email",
    "created": 1710000000.123,
    "payload": {"to": "test@test.com"},
    "execute_at": null,
    "retries": 0
  }
}
```

Encoding uses `JSON_INVALID_UTF8_IGNORE`. A `false` return from `json_encode()` throws `SerializeException`. Decoding requires `message.type`, `message.id`, and `message.created`. A payload that is present but not an array becomes `null`.

The message does not remember which priority it was sent with. Priority exists only as the argument to `send()` / `emit()`.

## Emitter

`Emitter` is what application code calls.

```php
public function __construct(DriverInterface $driver, ?LoggerInterface $logger = null)

public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
```

`emit()` calls `$driver->send($message, $priority)` and, when a logger was passed, logs at `info`:

```text
Emitter send message #<id> to driver <driver class>
```

The context array is `id`, `created`, `type`, and `payload`. Hermes 5.x wrote `Dispatcher send message …` here. The method returns `$this`.

`UnknownPriorityException` comes from the driver when `$priority` was never registered with `setupPriorityQueue()`.

## Dispatcher

`Dispatcher` is the worker.

```php
public function __construct(
    DriverInterface $driver,
    ?LoggerInterface $logger = null,
    ?ShutdownInterface $shutdown = null
)

public function handle(array $priorities = []): void
```

`DEFAULT_PRIORITY` is `100`. There is no `PRIORITY_HIGH` constant. The commented call in `examples/redis/processor.php` refers to a constant the class does not have.

`handle()` calls `Driver::wait()`. The closure receives the message and the priority the driver found (defaulting to `100` when a driver omits it). For each message the dispatcher:

1. Logs `Start handle message #<id> (<type>) priority:<n>` at `info`.
2. Runs every handler registered for `$message->getType()`, in registration order.
3. If a shutdown object was given and `shouldShutdown($startTime)` is true, throws `ShutdownException`.
4. Returns the handler result to the driver. None of the built-in drivers read that boolean.

When no handler is registered for the type, `dispatch()` returns `true` and does not log `End handle message`. The message is already gone from the queue.

`Dispatcher::emit()` still sends through the driver, but the method is deprecated. Use `Emitter`.

Handlers are replaced with `registerHandler()`, `registerHandlers()`, `unregisterHandler()`, and `unregisterAllHandlers()`. `unregisterHandler()` drops one object instance (`!==`) for one type. `unregisterAllHandlers()` clears every type. Both exist so tests can reuse a dispatcher. They were added in 4.2.0.

## Handler

```php
public function handle(MessageInterface $message): bool;
```

A handler does the slow work: mail, HTTP, files. Hermes calls it only from `Dispatcher`, which you run from CLI.

<Diagram name="concepts-3" alt="What a handler result means: true is success, false is a failure with no retry, an exception may send a retry copy" />

| Outcome | What Hermes does |
| --- | --- |
| `true` | Logs the end line. The combined result stays `true` if every handler returned `true`. |
| `false` | The combined result becomes `false`. Nothing is retried. |
| Exception or `Error` (any `Throwable`) | Logs at `error`, writes to Tracy when Tracy is enabled, then maybe [retries](/guide/retry). That handler returns `false` to the loop. Later handlers for the same type still run. |

If the dispatcher has a logger and the handler has `setLogger()` (for example `Psr\Log\LoggerAwareTrait`), the logger is injected before `handle()`.

`EchoHandler` is the built-in handler used by the examples. It prints the message and returns `true`. It also `use`s `RetryTrait`. `EchoHandler::handle()` itself does not throw. From 6.0 the class is `final`; write your own handler instead of extending it.

## Driver

```php
public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool;

public function setupPriorityQueue(string $name, int $priority): void;

public function wait(Closure $callback, array $priorities): void;
```

`send()` must return quickly. It runs in the web process. `wait()` blocks. It runs in the worker. The `$priorities` list is a filter of **priority integers**. An empty array means "every queue this driver knows". A non-empty array skips priorities that are not in the list. RabbitMQ and SQS accept the argument and do not use it.

`setupPriorityQueue()` on `LazyRabbitMqDriver` throws `NotSupportedException` with `LazyRabbitMqDriver is not supporting priority queues now`. `AmazonSqsDriver` throws `AmazonSQS is not supporting priority queues now`.

The built-in drivers also use three traits:

- `SerializerAwareTrait::setSerializer()` replaces `MessageSerializer`.
- `MaxItemsTrait::setMaxProcessItems()` stops `wait()` after N messages. `0` means unlimited.
- `ShutdownTrait::setShutdown()` lets `wait()` throw `ShutdownException` between messages.

`Dispatcher` calls `setShutdown()` on the driver when the driver has that method and you passed a shutdown object into the dispatcher.

## FAQ

### What is the difference between `Emitter` and `Dispatcher`?

`Emitter` runs in the web process and only sends messages to the driver. `Dispatcher` runs in the worker: it waits on the driver and calls the handlers registered for each message type.

### Does a message remember its priority?

No. Priority is only the second argument of `emit()` and `send()`. The serialized message has no priority field, so retries and delayed messages come back at the default priority 100.

### What can I put in the payload?

Anything that survives `json_encode()`: scalars and arrays. Do not put objects, resources, or connections in it. Pass ids and let the handler load fresh data.

### What happens when no handler is registered for a type?

`dispatch()` returns `true` and the message is gone. The start line is logged, the end line is not. Register handlers on every worker that reads that queue.

### Can several handlers run for one type?

Yes. Use `registerHandler()` more than once or `registerHandlers()`. They run in registration order, and an exception in one does not stop the others.
