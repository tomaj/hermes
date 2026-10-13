# Priority

Priority is an integer you pass to `emit()` or `send()`, and a name you bind to that integer with `setupPriorityQueue()`. Higher numbers are the ones you intend to run first. `Dispatcher::DEFAULT_PRIORITY` is `100`.

Only the Redis drivers implement it. RabbitMQ and SQS throw from `setupPriorityQueue()` and ignore the integer on `send()`.

```php
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter->emit(new Message('type1', ['a' => 'b']), Dispatcher::DEFAULT_PRIORITY - 10);
$emitter->emit(new Message('type1', ['c' => 'd']), Dispatcher::DEFAULT_PRIORITY + 10);
```

The constructor already registered the main key (`hermes` by default) at priority `100`. After the two calls the driver knows three keys: `hermes` at 100, `hermes_low` at 90, `hermes_high` at 110.

Register the same pairs on the worker. A priority that was not registered throws `UnknownPriorityException` (`Unknown priority 110` if 110 was never set up).

<Diagram name="priority-1" alt="Priority diagram" />

## Issue 50

[Issue #50](https://github.com/tomaj/hermes/issues/50) is open. `RedisSetDriver::wait()` and `PredisSetDriver::wait()` do this:

```php
$queues = array_reverse($this->queues, true);
```

PHP arrays remember insertion order. `array_reverse` with `true` flips that order and keeps the priority integers as keys. It does not `krsort` them. The issue asks for a numeric sort so a larger integer always wins. The current code does not do that.

The constructor inserts priority `100` first, so after the reverse it is **last**. Extra queues are polled in the reverse of the order you called `setupPriorityQueue()`.

The example above calls low (90) and then high (110). The worker therefore pops:

1. `hermes_high` (110)
2. `hermes_low` (90)
3. `hermes` (100)

90 runs before 100. That is the bug, not a second ordering rule. Calling high before low yields `90`, then `110`, then `100`. There is no call order that yields `110`, then `100`, then `90`, because `100` was inserted first and assigning the same key again does not move it.

`DummyDriver` in the test suite does `ksort` inside `setupPriorityQueue()` and then reverses, so its tests observe numeric order. `RedisSetDriver` does not `ksort`.

## A worker per priority

`handle()` can ignore every queue except the ones you list:

```php
$dispatcher->handle([Dispatcher::DEFAULT_PRIORITY + 10]);
```

The list is integers. `in_array($priority, $priorities)` decides. An empty list polls every registered queue, in the reversed insertion order above.

Run three CLI processes if you need numeric priority for real:

- one `handle([110])`
- one `handle([100])`
- one `handle([90])`

Give the 110 process more replicas than the others. Each process only `SPOP`s its own key. They no longer share one loop, so issue 50 does not reorder them.

## Other limits

- A set is unordered. Two messages on `hermes_high` come off with `SPOP`, which is not FIFO.
- Retries and promoted delayed messages call `send()` without a priority, so they join key `hermes` at 100.
- The message JSON has no priority field. A worker cannot recover the original integer from the payload.
