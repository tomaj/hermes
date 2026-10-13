---
title: "Retry"
description: "How RetryTrait and the dispatcher retry a failed PHP handler, the backoff formula, and what each driver does with it."
---

# Retry

A retry is a new `Message` published by `Dispatcher` after a handler throws. It is not a broker nack.

<Diagram name="retry-1" alt="A handler throws, the error is logged, and when retries remain a new message with a later executeAt is sent" />

The check is only `method_exists`. `RetryTrait::canRetry()` returns `true`, but `Dispatcher` never calls it. Overriding `canRetry()` to return `false` does not turn retry off. Omit the trait, or make `maxRetry()` return `0`.

Returning `false` from `handle()` does not retry. Only an exception does.

## RetryTrait

```php
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

class FlakyHandler implements HandlerInterface
{
    use RetryTrait;

    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('temporary failure');
    }

    public function maxRetry(): int
    {
        return 10;
    }
}
```

`RetryTrait::maxRetry()` returns `25` when you do not override it. `EchoHandler` uses the trait. Its `handle()` returns `true` and does not throw, so the trait does nothing until an exception escapes.

The new message keeps the original id, type, payload, and `created`. `retries` becomes `getRetries() + 1`. `executeAt` becomes the value below. `send()` is called **without** a priority, so Redis puts the copy on the schedule set and, once it is due, on the default priority key (`100`).

`HandleTest` covers the boundary. A message that already has `retries = 5` and a handler whose `maxRetry()` is `25` is republished with `retries = 6`. The same message with `maxRetry()` of `5` is not republished, because the test is `getRetries() < maxRetry()`. A handler that does not define `canRetry` and `maxRetry` is not republished either.

The copy is one `send()`. On Redis and Predis that `executeAt` parks the copy in the sorted set. On RabbitMQ and SQS the publish is immediate, so the backoff number is stored and not waited on.

<Diagram name="retry-2" alt="Sequence of a retry: the dispatcher sends a copy with retries plus one, Redis schedules it, RabbitMQ and SQS publish it at once" />

## The delay

`Dispatcher::nextRetry()` is the Sidekiq-style formula in the source:

```php
$retries = $message->getRetries();
return microtime(true) + $retries ** 4 + 15 + $this->randomizer->getInt(1, 30) * ($retries + 1);
```

`$retries` is the count **before** the increment. The jitter comes from a `Random\Randomizer`. By default it is `new Randomizer()`, which uses the cryptographically secure engine. Up to 5.x it was the global `rand()`.

| Current `retries` | Added delay, in seconds |
| --- | --- |
| 0 | 15 + a random integer from 1 to 30 |
| 1 | 16 + a random integer from 2 to 60 |
| 2 | 31 + a random integer from 3 to 90 |
| n | `n^4 + 15 + jitter(1, 30) * (n + 1)` |

Redis and Predis will wait, because `executeAt` is in the future. RabbitMQ and SQS publish the copy immediately. The timestamp is still inside the JSON, and the next failure still increments `retries`, but the worker can see the copy on the next poll. On those two drivers a failing handler can spin until `maxRetry()` is reached.

### Reproducible retry times

Pass your own `Randomizer` as the fourth `Dispatcher` argument. With a seeded engine the sequence of jitter values is the same on every run, which is useful in tests. The first retry the worker computes gets the first value, and so on. Which message that is depends on the delivery order, and the Redis drivers pop a random member of the set:

```php
use Random\Engine\Mt19937;
use Random\Randomizer;

$dispatcher = new Dispatcher($driver, $logger, null, new Randomizer(new Mt19937(42)));
```

Keep the default in production. Workers started with the same seed compute the same delays, so when an outage makes them all fail at once, their retries come back together on Redis and Predis. RabbitMQ and SQS ignore `executeAt`, so the jitter does not spread their retries in any case. `mt_srand()` does not affect the jitter any more, because it only seeds the global `rand()` that 5.x used.

A container that autowires `Dispatcher` injects a `Random\Randomizer` service if you registered one, for example a seeded one meant for something else. Pass `null` to keep the default. In Symfony, in `config/services.yaml`:

```yaml
services:
    Tomaj\Hermes\Dispatcher:
        arguments:
            $randomizer: null
```

In Nette, in `config/services.neon`:

```yaml
services:
	- Tomaj\Hermes\Dispatcher(randomizer: null)
```

## Several handlers

Every handler for the type is called, even after an earlier one throws. The exception is caught inside `handleMessage()`. Each throwing handler that has the two methods can publish its own copy. Two failing handlers produce two copies.

## What you do not get

- No retry when the process is killed after the driver has already removed the message and before `handle()` returns.
- No retry from a `false` return.
- No per-priority retry. The copy uses the default priority.
- No dead-letter queue inside Hermes. After the last allowed failure the method returns and the copy is not sent.

## FAQ

### How do I turn retries on?

Add `use RetryTrait;` to the handler, or define `canRetry()` and `maxRetry()` yourself, and throw an exception when the work fails.

### How do I turn retries off for one handler?

Remove the trait, or make `maxRetry()` return `0`. Returning `false` from `canRetry()` does not help, because the dispatcher only checks that the methods exist.

### How long until the next attempt?

`retries⁴ + 15 + jitter × (retries + 1)` seconds, with a random jitter from 1 to 30, so 16 to 45 seconds for the first retry. It grows quickly after that. Pass a seeded `Random\Randomizer` to the `Dispatcher` to make it reproducible.

### Why do retries happen instantly on RabbitMQ or SQS?

Those drivers ignore `executeAt`, so the copy is published at once. Use Predis for real backoff, or make the handler wait itself.

### Is there a dead-letter queue?

Not in Hermes. After the last allowed attempt the copy is simply not sent. Log the final failure in the handler if you need to act on it. See [Logging](/guide/logging). The brokers have their own: an SQS redrive policy and a RabbitMQ dead-letter exchange catch messages that the driver rejects or that keep crashing workers (see the [SQS](/guide/drivers/sqs) and [RabbitMQ](/guide/drivers/rabbitmq) pages).
