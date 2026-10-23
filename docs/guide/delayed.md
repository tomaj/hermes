---
title: "Delayed execution"
description: "Run a message later with executeAt on Redis and Predis, and why RabbitMQ, SQS and Kafka deliver at once."
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
$this->redis->zAdd($this->scheduleKey, $message->getExecuteAt(), $serialized);
```

The default schedule key is `hermes_schedule`. Change it with the fourth constructor argument if the default key is already used. The emitter and the worker must use the same key.

Each `wait()` iteration calls `zRangeByScore($scheduleKey, '-inf', $now, ['limit' => [0, 1]])`. For each returned member it calls `zRem()`. Only when `ZREM` reports that it removed the member does the driver re-send it, so two workers never promote the same entry. Up to 5.x this step used `SPOP`, which does not work on a sorted set, so due messages stayed in the schedule ([issue #95](https://github.com/tomaj/hermes/issues/95)).

After a successful `ZREM` the driver unserializes it and calls `send()` with no priority. `send()` sees the same `executeAt`. When that timestamp is still in the future the payload goes back to the sorted set. When it is due, `send()` writes the default priority set (`hermes` unless you changed the key). The original priority is not stored on the message, so a delayed message does not return to `hermes_high`.

## Predis

`send()` delays when `getExecuteAt()` is truthy and greater than now. The write is `zadd($scheduleKey, [$serialized => $executeAt])`.

`wait()` reads with `zrangebyscore` and `LIMIT OFFSET 0 COUNT 1`, then `zrem`s that exact member, then `send()`s it. That `zrem` is the 4.0.1 fix for [issue #48](https://github.com/tomaj/hermes/issues/48). Promotion still uses `send()` without a priority, so the due message joins the default set.

## FileSystem

`FileSystemDriver::send()` names the file after `executeAt`, or after the creation time when there is none. `wait()` reads the files in name order and stops at the first one that is not due, so a delayed message stays in `new/` of its own priority folder until then. See [FileSystem](/guide/drivers/filesystem#order-delay-and-several-workers).

## Retry uses the same field

`Dispatcher` sets `executeAt` from `nextRetry()` and calls `send()`. On Redis, Predis and the filesystem the retry waits. On RabbitMQ, SQS and Kafka it does not. The formula is on the [Retry](/guide/retry) page.

## FAQ

### How do I delay a message?

Pass a Unix timestamp as the fifth `Message` argument, `executeAt`, for example `microtime(true) + 3600`. The [delayed reminder](/examples/reminder) recipe shows a complete example.

### Which drivers support delays?

`RedisSetDriver` and `PredisSetDriver`. Both promote due messages with `ZREM`. In 5.x `RedisSetDriver` never promoted them ([issue #95](https://github.com/tomaj/hermes/issues/95)), so use Predis there. RabbitMQ, SQS and Kafka deliver at once.

### Where are delayed messages stored?

In the sorted set `hermes_schedule` by default, scored by `executeAt`. The emitter and the worker must use the same schedule key.

### Does a delayed message keep its priority?

No. When it is due, the driver calls `send()` without a priority, so it lands on the default priority 100.

### What if no worker is running when the message is due?

Nothing is lost. Due messages are moved only inside `wait()`, so the message runs as soon as a worker starts.
