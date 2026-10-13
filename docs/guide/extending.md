---
title: "Extending Hermes"
description: "Write your own Hermes driver, handler, serializer, or shutdown signal against the 5.x interfaces."
---

# Extending Hermes

The supported extension points are `DriverInterface`, `HandlerInterface`, `SerializerInterface`, and `ShutdownInterface`. The Gearman sketch in the README does not match the current method signatures (`$serialier` is not defined, and `wait()` now requires a priority list). Use the signatures from `src/Driver/DriverInterface.php`.

<Diagram name="extending-1" alt="Your own classes implementing DriverInterface, SerializerInterface, ShutdownInterface, and HandlerInterface" />

`MemoryDriver` in this diagram is an example you would write. It is not a class in the package. `MessageSerializer`, `SharedFileShutdown`, and `HandlerInterface` are.

## A driver

`send()` must be safe to call from a web request. `wait()` may block. Throw `UnknownPriorityException` for a priority you did not register. Throw `NotSupportedException` from `setupPriorityQueue()` if you only have one queue, the way RabbitMQ and SQS do.

This driver sorts priorities with `krsort` so a larger integer is popped first. The built-in Redis drivers do not. Doing it here avoids [issue #50](https://github.com/tomaj/hermes/issues/50) in your own backend.

```php
<?php

declare(strict_types=1);

use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\SerializerAwareTrait;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;

class MemoryDriver implements DriverInterface
{
    use SerializerAwareTrait;

    /** @var array<int, string> */
    private $queues = [];

    /** @var array<int, string[]> */
    private $items = [];

    public function __construct()
    {
        $this->serializer = new MessageSerializer();
        $this->setupPriorityQueue('default', Dispatcher::DEFAULT_PRIORITY);
    }

    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->queues[$priority] = $name;
        if (!isset($this->items[$priority])) {
            $this->items[$priority] = [];
        }
    }

    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        if (!isset($this->items[$priority])) {
            throw new UnknownPriorityException('Unknown priority ' . $priority);
        }

        $this->items[$priority][] = $this->serializer->serialize($message);

        return true;
    }

    public function wait(Closure $callback, array $priorities = []): void
    {
        $queues = $this->queues;
        krsort($queues, SORT_NUMERIC);

        foreach ($queues as $priority => $name) {
            if (count($priorities) > 0 && !in_array($priority, $priorities, true)) {
                continue;
            }

            foreach ($this->items[$priority] as $serialized) {
                $callback($this->serializer->unserialize($serialized), $priority);
            }
        }
    }
}
```

Call `$callback($message, $priority)`. The dispatcher logs that integer. If you omit it, the closure parameter defaults to `Dispatcher::DEFAULT_PRIORITY`.

`SerializerAwareTrait` gives you `setSerializer()`. The built-in drivers `use` it and construct a `MessageSerializer` themselves. Copy that if your driver should accept a replacement.

`MaxItemsTrait` and `ShutdownTrait` are optional. Use them if `wait()` loops. `Dispatcher` will call `setShutdown()` only when the method exists.

If you remove the message before the callback, document that, because the Redis and RabbitMQ drivers do and callers assume a crash loses the delivery. `AmazonSqsDriver` removes it only after the callback returns without an exception. If you remove it only after a `true` return, you are stricter than the built-in drivers. The dispatcher still ignores the boolean on the way back, so your driver has to read the callback's return value itself. The built-in drivers do not.

## A handler

A handler is one class per job type, or several classes on the same type via `registerHandler()` / `registerHandlers()`.

```php
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

class SendEmailHandler implements HandlerInterface
{
    use RetryTrait;

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!is_array($payload) || !isset($payload['to'], $payload['subject'], $payload['message'])) {
            return false;
        }

        mail($payload['to'], $payload['subject'], $payload['message']);

        return true;
    }

    public function maxRetry(): int
    {
        return 10;
    }
}
```

Add `LoggerAwareTrait` when the handler should receive the dispatcher's logger. See [Logging](/guide/logging).

## A serializer

`MessageSerializer` is the default and the one the other drivers understand. Replace it only on both sides, emitter and worker, or they will not decode each other.

```php
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\SerializerInterface;

class ExtraFieldSerializer implements SerializerInterface
{
    public function serialize(MessageInterface $message): string
    {
        $result = json_encode([
            'message' => [
                'id' => $message->getId(),
                'type' => $message->getType(),
                'created' => $message->getCreated(),
                'payload' => $message->getPayload(),
                'execute_at' => $message->getExecuteAt(),
                'retries' => $message->getRetries(),
            ],
        ]);

        if ($result === false) {
            throw new SerializeException('Cannot serialize message ' . $message->getId());
        }

        return $result;
    }

    public function unserialize(string $string): MessageInterface
    {
        $data = json_decode($string, true);
        if (!is_array($data) || !isset($data['message']) || !is_array($data['message'])) {
            throw new SerializeException('Cannot unserialize message');
        }

        $message = $data['message'];

        return new Message(
            $message['type'],
            isset($message['payload']) && is_array($message['payload']) ? $message['payload'] : null,
            $message['id'],
            (float) $message['created'],
            isset($message['execute_at']) && is_numeric($message['execute_at']) ? (float) $message['execute_at'] : null,
            isset($message['retries']) && is_numeric($message['retries']) ? (int) $message['retries'] : 0
        );
    }
}
```

```php
$driver->setSerializer(new ExtraFieldSerializer());
```

`setSerializer()` is not on `DriverInterface`. It exists on drivers that `use SerializerAwareTrait`. All four built-in drivers do.

## A shutdown signal

Implement `ShutdownInterface` when a file or a Redis key is the wrong switch. `shouldShutdown(DateTime $startTime)` is polled. `shutdown(?DateTime $shutdownTime = null)` is how another process asks for the stop. Return `true` from `shutdown()` when the signal was stored.

Keep the start-time rule if you can. Workers that start after the signal should keep running, or a rolling restart will shut down the new processes too. `RedisShutdown` does this. `SharedFileShutdown` does this by comparing `filemtime()` with the start timestamp.

## FAQ

### What can I replace?

The driver (`DriverInterface`), handlers (`HandlerInterface`), the serializer (`SerializerInterface`), and the shutdown signal (`ShutdownInterface`).

### Does the dispatcher read the handler result in my driver?

It returns it to your `wait()` callback. The built-in drivers ignore it. A custom driver can use it to acknowledge only on `true`.

### Do I have to change both sides when I replace the serializer?

Yes. The emitter and the worker must use the same serializer, or they cannot decode each other's messages.

### How should a custom shutdown signal treat new workers?

Ignore signals older than the worker's start time, like the built-in ones, so a rolling restart does not stop the new processes.

### Can I sort priorities numerically in my driver?

Yes. Sort the queue map with `krsort` before polling, as in the example on this page, to avoid issue [#50](https://github.com/tomaj/hermes/issues/50).
