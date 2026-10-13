---
title: "Redis (ext-redis) driver"
description: "RedisSetDriver with the phpredis extension: keys, the wait loop, priorities, and delayed messages."
---

# Redis (ext-redis)

`Tomaj\Hermes\Driver\RedisSetDriver` talks to a `Redis` instance from the phpredis extension. You open the connection yourself. The class does not call `connect()`.

```php
public function __construct(
    Redis $redis,
    string $key = 'hermes',
    int $refreshInterval = 1,
    string $scheduleKey = 'hermes_schedule'
)
```

The constructor registers `$key` at `Dispatcher::DEFAULT_PRIORITY` (`100`). `$refreshInterval` is how many seconds `wait()` sleeps when every selected set is empty. `0` disables that sleep. `$scheduleKey` is the sorted set used by [delayed execution](/guide/delayed).

## Emit and handle

From `examples/redis/emitter.php` and `examples/redis/processor.php`:

```php
use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Shutdown\RedisShutdown;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$driver = new RedisSetDriver($redis);
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter = new Emitter($driver);
$emitter->emit(
    new Message('type1', ['message' => 1]),
    Dispatcher::DEFAULT_PRIORITY + 10
);
```

```php
$driver = new RedisSetDriver($redis, 'hermes', 1);
$dispatcher = new Dispatcher($driver, null, new RedisShutdown($redis));
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`setupPriorityQueue()` must run on the emitter's driver and on the worker's driver. They do not share PHP objects. They share Redis keys. Unknown priorities throw `UnknownPriorityException` with the message `Unknown priority 999` (the number is the one you passed).

A second worker for one priority only:

```php
$dispatcher->handle([Dispatcher::DEFAULT_PRIORITY + 10]);
```

The argument is the integer priority, not the key name `hermes_high`.

Hermes does not use a Redis list. Live messages are set members. Delayed messages are sorted-set members. Each priority integer is its own set key. The default key is `hermes` at priority `100`. The schedule key defaults to `hermes_schedule`.

<Diagram name="drivers-redis-1" alt="Redis send: future messages go to the hermes_schedule sorted set, others to the set for their priority" />

`SPOP` removes one random member. It is not `LPOP`, and it is not FIFO. Names such as `hermes_high` are whatever you passed to `setupPriorityQueue()`. The integers above are the ones in `examples/redis`.

## What `send()` writes

```php
if ($message->getExecuteAt() !== null && $message->getExecuteAt() > microtime(true)) {
    $this->redis->zAdd($this->scheduleKey, $message->getExecuteAt(), $serialized);
} else {
    $this->redis->sAdd($this->getKey($priority), $serialized);
}
```

`zAdd` is score then member, which is the phpredis argument order. The live structure is a set (`SADD`), not a list.

## What `wait()` does

<Diagram name="drivers-redis-2" alt="Redis wait loop: read due schedule entries, SPOP the first priority set with a member, and call the handler" />

On an empty pass, and when `$refreshInterval` is not `0`, the driver sleeps that many seconds. It checks shutdown before the sleep as well as at the top of the loop.

`zRangeByScore` asks for one due member. `zRem()` then removes it, and only the worker whose `ZREM` returned `1` re-sends it, so parallel workers do not duplicate it. In 5.x this step called `sPop()` on the sorted set and due messages never left it ([issue #95](https://github.com/tomaj/hermes/issues/95)). See [Delayed execution](/guide/delayed).

Priority polling uses `array_reverse($this->queues, true)`. That reverses insertion order. It does not sort by the priority integer. [Priority](/guide/priority) walks through issue 50 with the keys from the example above.

`wait()` passes the priority it popped into the dispatcher callback. The callback's boolean is ignored. `incrementProcessedItems()` runs after the callback returns. A `ShutdownException` thrown from inside the callback skips that increment and ends `handle()`.

## Traits

`setSerializer()`, `setMaxProcessItems()`, and `setShutdown()` are available. Passing the shutdown object to `Dispatcher` also calls `setShutdown()` for you.

## FAQ

### Does the driver connect to Redis for me?

No. Create the `Redis` object, call `connect()`, and pass it to the constructor.

### Why do my delayed messages never run?

In 5.x the promotion step calls `SPOP` on the sorted schedule key, so due messages stay there. Upgrade to 6.0, or use `PredisSetDriver` on 5.x. See [Delayed execution](/guide/delayed).

### Are messages processed in order?

No. Each priority is a Redis set, and `SPOP` returns a random member.

### What does the refresh interval do?

It is how many seconds `wait()` sleeps when every queue is empty. `0` means no sleep, which keeps a CPU busy.

### Can I use a Predis client here?

No. Use `PredisSetDriver` for Predis. The two drivers were split in 4.0.0.
