---
title: "Priority"
description: "Priority queues on the Redis drivers, their numeric polling order, and running a worker per priority."
---

# Priority

Priority is an integer you pass to `emit()` or `send()`, and a name you bind to that integer with `setupPriorityQueue()`. Higher numbers are the ones you intend to run first. `Dispatcher::DEFAULT_PRIORITY` is `100`.

The Redis drivers and `FileSystemDriver` implement it. RabbitMQ, SQS and Kafka throw from `setupPriorityQueue()` and ignore the integer on `send()`.

```php
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter->emit(new Message('type1', ['a' => 'b']), Dispatcher::DEFAULT_PRIORITY - 10);
$emitter->emit(new Message('type1', ['c' => 'd']), Dispatcher::DEFAULT_PRIORITY + 10);
```

The constructor already registered the main key (`hermes` by default) at priority `100`. After the two calls the driver knows three keys: `hermes` at 100, `hermes_low` at 90, `hermes_high` at 110.

Register the same pairs on the worker. A priority that was not registered throws `UnknownPriorityException` (`Unknown priority 110` if 110 was never set up).

<Diagram name="priority-1" alt="Priority queues can be registered in any order; the worker sorts them and polls the highest number first" />

## Polling order

`RedisSetDriver::wait()` and `PredisSetDriver::wait()` sort the registered queues by priority number, highest first:

```php
$queues = $this->queues;
krsort($queues, SORT_NUMERIC);
```

The order of the `setupPriorityQueue()` calls does not matter. The example above calls low (90) and then high (110), and the worker pops:

1. `hermes_high` (110)
2. `hermes` (100)
3. `hermes_low` (90)

Each `wait()` iteration starts again from the top, so a waiting 110 message always runs before a 100 one.

### Before 6.0

Up to 5.x the drivers used `array_reverse($this->queues, true)`, which polls in reverse registration order, not by number ([issue #50](https://github.com/tomaj/hermes/issues/50)). The constructor registers 100 first, so it was always polled last, and the example above popped 110, then 90, then 100. On 5.x, register only priorities above 100, in ascending order, or run a worker per priority.

## A worker per priority

`handle()` can ignore every queue except the ones you list:

```php
$dispatcher->handle([Dispatcher::DEFAULT_PRIORITY + 10]);
```

The list is integers. `Dispatcher::handle()` casts integer strings such as `'110'` from argv or a console option to `int` (any other string throws `InvalidArgumentException`), and the Redis drivers then compare strictly (`in_array($priority, $priorities, true)`). A driver called directly through `wait()` gets no cast, so pass integers there. An empty list polls every registered queue, highest number first.

Run three CLI processes if you want to scale priorities separately:

- one `handle([110])`
- one `handle([100])`
- one `handle([90])`

Give the 110 process more replicas than the others. Each process only `SPOP`s its own key. A burst of low-priority work then cannot delay high-priority work at all.

## Other limits

- A set is unordered. Two messages on `hermes_high` come off with `SPOP`, which is not FIFO.
- Retries and promoted delayed messages call `send()` without a priority, so they join key `hermes` at 100.
- The message JSON has no priority field. A worker cannot recover the original integer from the payload.

## FAQ

### Which drivers support priorities?

`RedisSetDriver`, `PredisSetDriver` and `FileSystemDriver`. RabbitMQ, SQS and Kafka throw `NotSupportedException` from `setupPriorityQueue()`.

### Why did a lower priority run first?

On 6.0 a lower number runs first only when no higher-priority message is waiting. On 5.x the Redis drivers polled queues in reverse registration order, not by number, so priority 100 (registered first by the constructor) was polled last. See issue [#50](https://github.com/tomaj/hermes/issues/50) and the [priority recipe](/examples/priority).

### How do I get strict numeric order?

From 6.0 the drivers poll in numeric order, highest first. On 5.x, run one worker per priority with `handle([110])`, `handle([100])`, and so on, or register only priorities above 100, in ascending order.

### Do I have to register priorities on both sides?

Yes. The emitter and every worker call `setupPriorityQueue()` with the same pairs. An unknown priority throws `UnknownPriorityException`.

### Are messages within one priority FIFO?

No. Each priority is a Redis set, and `SPOP` returns a random member.
