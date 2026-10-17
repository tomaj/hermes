---
title: "Shutdown and max items"
description: "Stop Hermes workers gracefully with a file or a Redis key, and recycle them after N messages."
---

# Shutdown and max items

Hermes can leave `Dispatcher::handle()`. It cannot start the process again. Supervisor, systemd, or Kubernetes does that. The old `Restart*` names were removed in 4.0.0.

<Diagram name="shutdown-1" alt="The worker loop checks the shutdown signal and max items, then exits so the process manager starts it again" />

Pass the shutdown object to the dispatcher. If the driver has `setShutdown()`, the constructor calls it, so both checks run. The four built-in drivers use `ShutdownTrait`.

```php
$dispatcher = new Dispatcher($driver, $logger, $shutdown);
```

The examples also call `$driver->setShutdown()` themselves. That is enough for the driver's own check. The dispatcher check after each message runs only when the dispatcher was given the object too.

`ShutdownException` extends `HermesException`. `handle()` catches it, logs `Exiting hermes dispatcher - shutdown` at `notice`, and returns. Any other `Exception` that escapes `wait()` is logged at `error` as `Exiting hermes dispatcher - driver error: <class> - <message>`, passed to `Debugger::log($exception, Debugger::EXCEPTION)` when `Tracy\Debugger::isEnabled()` is true, and rethrown, so the worker exits non-zero ([#113](https://github.com/tomaj/hermes/issues/113)). Hermes does not call `Debugger::enable()` for you.

<Diagram name="shutdown-2" alt="Sequence: an operator sets the shutdown signal, the running worker finishes its message and exits, and the manager starts a new one" />

## SharedFileShutdown

Any driver can use a file.

```php
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

$shutdown = new SharedFileShutdown('/tmp/hermes_shutdown');
$dispatcher = new Dispatcher($driver, $logger, $shutdown);
```

Stop the workers:

```php
$shutdown->shutdown();
```

`shutdown()` defaults the time to `new DateTime()`. It writes that time with microseconds (`1700000000.123456`) into the file and sets the file's modification time to the same second. Like `touch` in 5.x it rewrites an existing file in place, so the file keeps its owner and mode and the directory does not have to be writable. Unlike `touch`, it needs write access to the file. Workers only need to read the file; one that cannot read it uses its modification time. `shouldShutdown()` returns true when the file exists, the time in it is not in the future, and it is later than the worker's start time. A missing file means keep running. A worker that started after the shutdown keeps running, even when it started in the same second.

`touch` works too. When the file holds no time, or was touched in a later second than the last `shutdown()`, its modification time is used instead. That is a whole second and counts as the start of that second, so a worker that started earlier in that same second keeps running.

## RedisShutdown

For a phpredis `Redis` client. The default key is `hermes_shutdown`.

```php
use Tomaj\Hermes\Shutdown\RedisShutdown;

$shutdown = new RedisShutdown($redis);
$dispatcher = new Dispatcher($driver, null, $shutdown);
```

`examples/redis/shutdown.php` is `new RedisShutdown($redis)->shutdown()`.

`shutdown()` `SET`s the key to the time with microseconds (`1700000000.123456`). `shouldShutdown()` returns true only when all of these hold:

- `GET` returned a number (a missing key or any other value means keep running)
- the time is not in the future (a future value does not stop anyone yet)
- the time is later than the worker's start time (a worker that started afterwards keeps running, even in the same second)

A whole-second value, such as one written by Hermes 5.x, counts as the start of that second.

## PredisShutdown

Same key and the same three rules, with `Predis\Client`. `GET` of a missing key is `null` rather than `false`. `shutdown()` returns true when the Predis status payload is `OK`. Use this class with `PredisSetDriver`. `examples/predis/shutdown.php` constructs a driver and then calls `PredisShutdown::shutdown()`. The driver construction is not required for the `SET`. The shutdown object only needs the client and the key.

```php
use Predis\Client;
use Tomaj\Hermes\Shutdown\PredisShutdown;

$redis = new Client([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
]);

new PredisShutdown($redis)->shutdown();
```

One key stops every worker that was started before that time and that polls the same Redis. New workers started by the process manager see a time older than their start time and stay up. Delete the key when you want a later deploy to use a fresh signal. Leaving it in place is safe for workers that start afterwards. It is not safe for a worker whose start time is still earlier.

All three shutdowns compare the stored time with the worker host's clock. Keep the clocks of the hosts that call `shutdown()` and the hosts that run workers in sync (NTP). A request from a host whose clock is ahead counts as in the future and stops workers only once their clock reaches it. A request from a host whose clock is behind does not stop workers that started within that difference before the request. Hermes 5.x had the same dependency on the clocks.

## Max items

`MaxItemsTrait` is on all four drivers.

```php
$driver->setMaxProcessItems(1000);
```

`0`, the default, means `shouldProcessNext()` is always true. After `processed` reaches the limit, `wait()` returns on the next loop check. Redis, Predis, and SQS `break`. RabbitMQ breaks both loops and then closes the channel and the connection. The count increments after a callback returns, so the message that hits the limit is still handled.

The process then exits `handle()` with no `ShutdownException`. A process manager with `autorestart` or `Restart=always` starts a new one. That is the memory-cap pattern: recycle every N messages instead of running one PHP process forever.

`processed()` returns the current count. `incrementProcessedItems()` is public and is what the drivers call. You do not need to call it yourself.

## FAQ

### How do I stop workers after a deploy?

Call `shutdown()` on the same `RedisShutdown`, `PredisShutdown`, or `SharedFileShutdown` that the workers use. Each worker finishes its current message and returns from `handle()`.

### Will the new workers stop too?

No. Workers that started after the shutdown time keep running, because the signal is compared with each worker's start time.

### Does Hermes restart the process?

No. Use Supervisor, systemd, or Kubernetes to start a new process when the old one exits. See [Production deployment](/guide/deployment).

### What does `setMaxProcessItems()` do?

It makes `wait()` return after N handled messages, so the process exits cleanly and the process manager starts a fresh one. `0` means no limit.

### Does Hermes handle SIGTERM?

No. There is no `pcntl` handler. A process killed during `handle()` loses the message it already took, so give the process manager a stop timeout longer than one message.
