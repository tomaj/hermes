# Shutdown and max items

Hermes can leave `Dispatcher::handle()`. It cannot start the process again. Supervisor, systemd, or Kubernetes does that. The old `Restart*` names were removed in 4.0.0.

<Diagram name="shutdown-1" alt="Shutdown and max items diagram" />

Pass the shutdown object to the dispatcher. If the driver has `setShutdown()`, the constructor calls it, so both checks run. The four built-in drivers use `ShutdownTrait`.

```php
$dispatcher = new Dispatcher($driver, $logger, $shutdown);
```

The examples also call `$driver->setShutdown()` themselves. That is enough for the driver's own check. The dispatcher check after each message runs only when the dispatcher was given the object too.

`ShutdownException` extends `HermesException`. `handle()` catches it, logs `Exiting hermes dispatcher - shutdown` at `notice`, and returns. Other exceptions that escape `wait()` are caught separately. When `Tracy\Debugger::isEnabled()` is true they are passed to `Debugger::log($exception, Debugger::EXCEPTION)`. Hermes does not call `Debugger::enable()` for you.

<Diagram name="shutdown-2" alt="Shutdown and max items diagram" />

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

`shutdown()` defaults the time to `new DateTime()` and `touch()`es the file with that unix timestamp. `shouldShutdown()` calls `clearstatcache()` for that path, then returns true when the file exists and `filemtime()` is greater than or equal to the worker start timestamp. A missing file means keep running. A file that is older than the start time means keep running, so a worker that starts after the touch does not exit.

`filemtime()` is a whole second. A touch in the same second as process start can be equal to the start timestamp and will shut the worker down, because the comparison is `>=`.

A future timestamp also shuts the worker down immediately if it is already `>=` the start time. That differs from the Redis helpers below.

## RedisShutdown

For a phpredis `Redis` client. The default key is `hermes_shutdown`.

```php
use Tomaj\Hermes\Shutdown\RedisShutdown;

$shutdown = new RedisShutdown($redis);
$dispatcher = new Dispatcher($driver, null, $shutdown);
```

`examples/redis/shutdown.php` is `(new RedisShutdown($redis))->shutdown()`.

`shutdown()` `SET`s the key to the unix timestamp. `shouldShutdown()` returns true only when all of these hold:

- `GET` returned a value (`false` means the key is missing)
- the timestamp is not greater than `time()` (a future value does not stop anyone yet)
- the timestamp is greater than or equal to the worker start timestamp (a worker that started later keeps running)

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

(new PredisShutdown($redis))->shutdown();
```

One key stops every worker that was started before that timestamp and that polls the same Redis. New workers started by the process manager see a timestamp older than their start time and stay up. Delete the key when you want a later deploy to use a fresh signal. Leaving it in place is safe for workers that start afterwards. It is not safe for a worker whose start timestamp is still earlier.

## Max items

`MaxItemsTrait` is on all four drivers.

```php
$driver->setMaxProcessItems(1000);
```

`0`, the default, means `shouldProcessNext()` is always true. After `processed` reaches the limit, `wait()` returns on the next loop check. Redis, Predis, and SQS `break`. RabbitMQ breaks both loops and then closes the channel and the connection. The count increments after a callback returns, so the message that hits the limit is still handled.

The process then exits `handle()` with no `ShutdownException`. A process manager with `autorestart` or `Restart=always` starts a new one. That is the memory-cap pattern: recycle every N messages instead of running one PHP process forever.

`processed()` returns the current count. `incrementProcessedItems()` is public and is what the drivers call. You do not need to call it yourself.
