# Predis

`Tomaj\Hermes\Driver\PredisSetDriver` is the same Redis layout as `RedisSetDriver`, with a `Predis\Client` and Predis method names. Use this class when you cannot load `ext-redis`. Do not pass a Predis client to `RedisSetDriver`. The 4.0.0 split made those two types separate.

```php
public function __construct(
    Client $redis,
    string $key = 'hermes',
    int $refreshInterval = 1,
    string $scheduleKey = 'hermes_schedule'
)
```

## Emit and handle

This is `examples/predis`:

```php
use Predis\Client;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Shutdown\PredisShutdown;

$redis = new Client([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
]);

$driver = new PredisSetDriver($redis);
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter = new Emitter($driver);
$emitter->emit(new Message('type1', ['message' => 1]), Dispatcher::DEFAULT_PRIORITY);
```

```php
$driver = new PredisSetDriver($redis, 'hermes', 1);
$dispatcher = new Dispatcher($driver, null, new PredisShutdown($redis));
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`examples/predis/shutdown.php` stops workers with:

```php
(new PredisShutdown($redis))->shutdown();
```

## Commands

A due message, or a message with no `executeAt`, is `sadd` on the priority key. A future message is:

```php
$this->redis->zadd($this->scheduleKey, [
    $this->serializer->serialize($message) => $message->getExecuteAt(),
]);
```

That member-to-score map is the Predis 1.1 shape. It is the fix for [issue #48](https://github.com/tomaj/hermes/issues/48), where a retry was scheduled with the arguments the other way around.

`send()` treats `executeAt` as "delay this" only when the value is truthy **and** greater than `microtime(true)`. A `null` execute-at is not delayed.

## Wait loop

<Diagram name="drivers-predis-1" alt="Wait loop diagram" />

`zrangebyscore` uses `LIMIT OFFSET 0 COUNT 1`. Each returned member is removed with `zrem` and passed back through `send()`. If it is still in the future it returns to the sorted set. If it is due, `send()` `sadd`s it onto the **default** priority key, because `send()` is called with no priority argument.

The live queue is still a set. `spop` is not FIFO. Empty loops sleep `$refreshInterval` seconds when that value is not `0`.

Priority order is the same reverse-insertion behavior as ext-redis. Read [Priority](/guide/priority) before you depend on high numbers running first.

Shutdown checks use `ShutdownTrait`. Pair this driver with `PredisShutdown`, not `RedisShutdown`. `PredisShutdown::shutdown()` treats a Predis status payload of `OK` as success. `shouldShutdown()` treats a missing key (`null` from `get`) as "keep running".
