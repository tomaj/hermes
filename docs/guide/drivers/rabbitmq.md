---
title: "RabbitMQ driver"
description: "LazyRabbitMqDriver for Hermes: durable queue, persistent publishing, manual acknowledgements, and its limits."
---

# RabbitMQ

`Tomaj\Hermes\Driver\LazyRabbitMqDriver` takes any php-amqplib connection (`PhpAmqpLib\Connection\AbstractConnection`). The driver is lazy: it opens the channel on the first `send()` or `wait()`, inside `getChannel()`. Pass a lazy connection too, so that building the driver does not connect: `AMQPLazyConnection`, as below, or, since php-amqplib deprecated that class, `AMQPConnectionFactory::create()` with `AMQPConnectionConfig::setIsLazy(true)`. Hermes 5.x required `AMQPLazyConnection`.

```php
public function __construct(
    AbstractConnection $connection,
    string $queue,
    array $amqpMessageProperties = [],
    int $refreshInterval = 0,
    string $consumerTag = 'hermes',
    bool $durable = true
)
```

`examples/rabbitmq` connects as the default guest user:

```php
use PhpAmqpLib\Connection\AMQPLazyConnection;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;

$connection = new AMQPLazyConnection('localhost', '5672', 'guest', 'guest', '/');
$driver = new LazyRabbitMqDriver($connection, 'hermes_queue');

$emitter = new Emitter($driver);
$emitter->emit(new Message('type1', ['message' => 1]));
```

```php
$driver = new LazyRabbitMqDriver($connection, 'hermes_queue', [], 0);
$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

There is one queue and the default exchange. Hermes does not declare an exchange of its own. `basic_publish` uses an empty exchange name and the queue name as the routing key, which is the broker's default exchange. `basic_consume` uses manual acknowledgements: the worker sends `basic_ack` only after your handler finished, so a crashed worker's message goes back to the queue.

<Diagram name="drivers-rabbitmq-1" alt="RabbitMQ publish path: Emitter, basic_publish, default exchange, durable queue, basic_consume with manual ack, Handler" />

## Queue and publish

`getChannel()` declares the queue on first use:

```php
$this->channel->queue_declare($this->queue, false, $this->durable, false, false);
```

Those flags are passive `false`, durable `$durable` (default `true`), exclusive `false`, auto-delete `false`. From 6.0 the queue survives a broker restart ([issue #70](https://github.com/tomaj/hermes/issues/70)).

`send()` builds an `AMQPMessage` with `$amqpMessageProperties` and publishes it to the default exchange, with the queue name as the routing key:

```php
$this->getChannel()->basic_publish($rabbitMessage, '', $this->queue);
```

`$priority` is accepted and not used. `setupPriorityQueue()` always throws `NotSupportedException`.

With `$durable = true` the driver also publishes persistent messages (`delivery_mode` 2), unless you set `delivery_mode` yourself in `$amqpMessageProperties`.

### Upgrading a 5.x queue

Hermes 5.x declared the queue non-durable. RabbitMQ refuses to redeclare an existing queue with different flags and closes the channel with `PRECONDITION_FAILED`. The emitter and the worker both declare the queue: `emit()` throws, and the worker logs `Exiting hermes dispatcher - driver error: ...` at `error` and exits non-zero, because `Dispatcher::handle()` rethrows driver errors. You have two options:

- Stop the producers, let the workers drain the old queue, delete it (`rabbitmqctl delete_queue --if-empty hermes_queue`), and let 6.0 declare it durable. A 5.x process cannot use the durable queue, so rolling back needs the same steps.
- Keep the old queue and opt out:

```php
$driver = new LazyRabbitMqDriver($connection, 'hermes_queue', [], 0, 'hermes', durable: false);
```

With `durable: false` the queue is non-durable and messages are not marked persistent, as in 5.x. Acknowledgements stay manual.

**RabbitMQ 4.3 and newer:** the broker no longer lets clients create non-durable, non-exclusive queues by default (deprecated feature `transient_nonexcl_queues`; 4.3.6 refuses them, 4.0.5 still allowed them with a warning). The declaration fails with `INTERNAL_ERROR` and the broker closes the connection. That breaks a 5.x emitter or worker, which declares a non-durable queue, and Hermes with `durable: false`. Non-durable queues also disappear when the broker restarts, so after the upgrade to 4.3 no old queue is left to share. Either deploy 6.0 with the default durable queue, or allow the feature again in `rabbitmq.conf` until every process runs 6.0 with durable queues:

```ini
deprecated_features.permit.transient_nonexcl_queues = true
```

The setting takes effect after a broker restart, and a restart deletes non-durable queues with their messages: drain them first.

`executeAt` is serialized and then ignored. The publish happens immediately. [Retry](/guide/retry) on this driver republishes immediately too.

## Consume

<Diagram name="drivers-rabbitmq-2" alt="Sequence: the worker declares a durable queue, consumes with prefetch 1, passes each body to the callback, then acknowledges it" />

`wait()` first sets `basic_qos` with a prefetch count of `1`, so the broker hands each worker one unacknowledged message at a time. The `basic_consume` arguments are the queue, `$consumerTag` (default `hermes`), `no_local` false, `no_ack` false, exclusive false, nowait false, and the callback. For each delivery:

- The body is unserialized and passed to the dispatcher callback. When it returns, the driver calls `basic_ack` and counts the message for max items.
- If the callback throws, the driver calls `basic_nack` with requeue, so the message goes back to the queue, and rethrows.
- If the body cannot be unserialized, the driver calls `basic_nack` without requeue, so a broken message is not redelivered forever. To keep it, add a dead-letter exchange with a policy (`rabbitmqctl set_policy -p / hermes-dlx '^hermes$' '{"dead-letter-exchange":"hermes-dlx"}' --apply-to queues`, with your queue name in the pattern; the `hermes-dlx` exchange needs a bound queue, and only the highest-priority policy applies to a queue). Do not use queue arguments: the driver declares the queue without arguments, so `x-dead-letter-exchange` or `x-queue-type` make the declaration fail with `PRECONDITION_FAILED`.
- If the worker dies before the ack, RabbitMQ redelivers the message when the connection closes.

Delivery is at least once, so handlers should be idempotent. The dispatcher catches handler exceptions itself, so a handler that throws any `Throwable` (an `Exception` or a PHP `Error` such as `TypeError`) is still acknowledged ([#112](https://github.com/tomaj/hermes/issues/112)). [Retry](/guide/retry) publishes a new copy for those.

The consume loop calls `AMQPChannel::wait(null, false, $timeout)`, which blocks until a delivery arrives or the timeout passes. A delivery is handled at once, so the timeout does not slow a busy queue. On a timeout the driver checks shutdown and waits again, so an idle worker uses no CPU. `$refreshInterval` is that timeout in seconds; `0`, the default, means one second. The example passes `0`. A longer timeout makes an idle worker notice a [shutdown](/guide/shutdown) later:

```php
new LazyRabbitMqDriver($connection, 'hermes_queue', [], 5, 'hermes-worker-1');
```

Before 6.0 the loop polled without blocking and slept `$refreshInterval` seconds after every wait, so `0` spun one CPU core on an idle queue and any other value delayed every message.

`$priorities` on `wait()` is unused.

When max items is reached, both loops break, then the driver closes the channel and the connection. A shutdown throws `ShutdownException` before that close. `Dispatcher::handle()` catches it, logs `Exiting hermes dispatcher - shutdown`, and returns.

`MaxItemsTrait`, `ShutdownTrait`, and `SerializerAwareTrait` are on this driver. `SharedFileShutdown` does not need RabbitMQ. `RedisShutdown` still needs a Redis client, which this driver does not have.

## FAQ

### Are messages persistent?

Yes, from 6.0. The queue is durable and messages are published with `delivery_mode` 2. Pass `durable: false` to keep a 5.x non-durable queue, on a broker that still allows non-durable queues (RabbitMQ 4.3 does not by default, see [Upgrading a 5.x queue](#upgrading-a-5-x-queue)). On 5.x the queue was non-durable ([issue #70](https://github.com/tomaj/hermes/issues/70)).

### Does the driver acknowledge messages?

Yes, from 6.0. It sends `basic_ack` after the handler finished and `basic_nack` when the callback fails. 5.x consumed with `no_ack = true`.

### Do retries wait?

No. `executeAt` is ignored, so a retry copy is published at once.

### Can I use priorities?

No. `setupPriorityQueue()` throws `NotSupportedException`. Use separate queues and workers instead.

### Why does my worker use 100% CPU?

On 6.0 it should not: an idle worker blocks in the channel wait. Before 6.0 the default refresh interval `0` made the consume loop poll without a pause; there, pass `1` as the fourth constructor argument.
