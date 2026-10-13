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
return microtime(true) + pow($message->getRetries(), 4) + 15 + (rand(1, 30) * ($message->getRetries() + 1));
```

`$message->getRetries()` is the count **before** the increment. `rand()` is PHP's `rand`.

| Current `retries` | Added delay, in seconds |
| --- | --- |
| 0 | 15 + a random integer from 1 to 30 |
| 1 | 16 + a random integer from 2 to 60 |
| 2 | 31 + a random integer from 3 to 90 |
| n | `n^4 + 15 + rand(1, 30) * (n + 1)` |

Redis and Predis will wait, because `executeAt` is in the future. RabbitMQ and SQS publish the copy immediately. The timestamp is still inside the JSON, and the next failure still increments `retries`, but the worker can see the copy on the next poll. On those two drivers a failing handler can spin until `maxRetry()` is reached.

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

`retries⁴ + 15 + rand(1, 30) × (retries + 1)` seconds, so 16 to 45 seconds for the first retry. It grows quickly after that.

### Why do retries happen instantly on RabbitMQ or SQS?

Those drivers ignore `executeAt`, so the copy is published at once. Use Predis for real backoff, or make the handler wait itself.

### Is there a dead-letter queue?

No. After the last allowed attempt the copy is simply not sent. Log the final failure in the handler if you need to act on it. See [Logging](/guide/logging).
