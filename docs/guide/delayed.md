---
title: "Delayed execution"
description: "Run a message later with executeAt on Redis, Predis and the filesystem, and why RabbitMQ, SQS and Kafka deliver at once."
---

# Delayed execution

Since 2.0 a message can carry `executeAt`, a `microtime(true)` timestamp. The fourth constructor argument is `created`. The fifth is `executeAt`.

```php
$executeAt = microtime(true) + 60;
$message = new Message('send-email', [
    'to' => 'test@test.com',
    'subject' => 'Later',
    'message' => 'Hello from hermes!',
], executeAt: $executeAt);

$emitter->emit($message);
```

<Diagram name="delayed-1" alt="Driver.send checks executeAt: Redis and Predis put future messages in the schedule set, the worker moves them to the live queue when due" />

Supported drivers are `RedisSetDriver`, `PredisSetDriver` and `FileSystemDriver`. `LazyRabbitMqDriver::send()`, `AmazonSqsDriver::send()` and `KafkaDriver::send()` do not read `getExecuteAt()`.

## Redis extension

`send()` delays when `getExecuteAt()` is not `null` and is greater than `microtime(true)`:

```php
$this->redis->zAdd($this->scheduleKey($priority), $message->getExecuteAt(), $serialized);
```

The default schedule key is `hermes_schedule`. Change it with the fourth constructor argument if the default key is already used. The emitter and the worker must use the same key. A message with the default priority is scheduled in that key; any other priority in its own sorted set, `<schedule key>:<priority>` (for example `hermes_schedule:110`). `send()` throws `UnknownPriorityException` for a priority that `setupPriorityQueue()` never registered, delayed or not.

Each `wait()` iteration calls `zRangeByScore($scheduleKey, '-inf', $now, ['limit' => [0, 1]])` on the schedule key of every registered priority, also of the priorities the worker does not handle, so a worker started with `handle([110])` still promotes the delayed messages of priority 100. For each returned member it calls `zRem()`. Only when `ZREM` reports that it removed the member does the driver re-send it, so two workers never promote the same entry. Up to 5.x this step used `SPOP`, which does not work on a sorted set, so due messages stayed in the schedule ([issue #95](https://github.com/tomaj/hermes/issues/95)).

After a successful `ZREM` the driver unserializes it and calls `send()` with the priority of that schedule key. `send()` sees the same `executeAt` and compares it with a clock read taken after `ZRANGEBYSCORE`, so the message is due and `send()` writes it to the set of its priority: a message delayed at 110 returns to `hermes_high`. Up to 6.0 there was one schedule key and every delayed message was promoted to the default priority. Entries that 6.0 wrote are in `hermes_schedule` and are still promoted, at the default priority ([#183](https://github.com/tomaj/hermes/issues/183)).

## Predis

`send()` delays when `getExecuteAt()` is truthy and greater than now. The write is `zadd($this->scheduleKey($priority), [$serialized => $executeAt])`.

`send()` uses the same schedule key per priority as the Redis extension driver. `wait()` reads every schedule key with `zrangebyscore` and `LIMIT OFFSET 0 COUNT 1`, then `zrem`s that exact member. Only when `zrem` returns `1` does it `send()` the member, so two workers never promote the same entry. Up to 5.x Predis ignored the `zrem` result, and concurrent workers could deliver one delayed message twice. The `zrem` itself is the 4.0.1 fix for [issue #48](https://github.com/tomaj/hermes/issues/48). Promotion calls `send()` with the priority of the schedule key, so the due message joins the set of its own priority.

## FileSystem

`FileSystemDriver::send()` names the file after `executeAt`, or after the creation time when there is none. `wait()` reads the files in name order and stops at the first one that is not due, so a delayed message stays in `new/` of its own priority folder until then. See [FileSystem](/guide/drivers/filesystem#order-delay-and-several-workers).

## Retry uses the same field

`Dispatcher` sets `executeAt` from `nextRetry()` and calls `send()`. On Redis, Predis and the filesystem the retry waits. On RabbitMQ, SQS and Kafka it does not. The formula is on the [Retry](/guide/retry) page.

## FAQ

### How do I delay a message?

Pass a Unix timestamp as the fifth `Message` argument, `executeAt`, for example `microtime(true) + 3600`. The [delayed reminder](/examples/reminder) recipe shows a complete example.

### Which drivers support delays?

`RedisSetDriver`, `PredisSetDriver` and `FileSystemDriver`. The Redis drivers promote due messages with `ZREM`. In 5.x `RedisSetDriver` never promoted them ([issue #95](https://github.com/tomaj/hermes/issues/95)), so use Predis there. `FileSystemDriver` encodes the due time in the file name. RabbitMQ, SQS and Kafka deliver at once.

### Where are delayed messages stored?

On Redis and Predis, in the sorted set `hermes_schedule` by default (`hermes_schedule:<priority>` for a priority other than 100), scored by `executeAt`. The emitter and the worker must use the same schedule key. On `FileSystemDriver`, delayed messages stay in `new/` of their priority folder until the due time in the file name.

### Does a delayed message keep its priority?

Yes. The Redis drivers keep one schedule set per priority and promote a due message with that priority, and `FileSystemDriver` keeps it in the folder of its priority. Up to 6.0 the Redis drivers promoted every delayed message to the default priority 100 ([#183](https://github.com/tomaj/hermes/issues/183)).

### What if no worker is running when the message is due?

Nothing is lost. Due messages are moved only inside `wait()`, so the message runs as soon as a worker starts.
