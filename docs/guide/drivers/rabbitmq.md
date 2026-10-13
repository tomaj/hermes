# RabbitMQ

`Tomaj\Hermes\Driver\LazyRabbitMqDriver` uses `PhpAmqpLib\Connection\AMQPLazyConnection`. The connection is lazy: the channel is opened on the first `send()` or `wait()`, inside `getChannel()`.

```php
public function __construct(
    AMQPLazyConnection $connection,
    string $queue,
    array $amqpMessageProperties = [],
    int $refreshInterval = 0,
    string $consumerTag = 'hermes'
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

There is one queue and the default exchange. Hermes does not declare an exchange of its own. `basic_publish` uses an empty exchange name and the queue name as the routing key, which is the broker's default exchange. `basic_consume` is called with `no_ack` true, so the delivery is settled before your handler runs. There is no `basic_ack` and no `basic_nack`.

<Diagram name="drivers-rabbitmq-1" alt="RabbitMQ diagram" />

## Queue and publish

`getChannel()` declares the queue on first use:

```php
$this->channel->queue_declare($this->queue, false, false, false, false);
```

Those flags are passive `false`, durable `false`, exclusive `false`, auto-delete `false`. The queue is not durable.

`send()` builds an `AMQPMessage` with `$amqpMessageProperties` and publishes it to the default exchange, with the queue name as the routing key:

```php
$this->getChannel()->basic_publish($rabbitMessage, '', $this->queue);
```

`$priority` is accepted and not used. `setupPriorityQueue()` always throws `NotSupportedException`.

To ask RabbitMQ for persistent **messages**, pass the property the library already forwards:

```php
$driver = new LazyRabbitMqDriver($connection, 'hermes_queue', [
    'delivery_mode' => 2,
]);
```

The queue declaration is still non-durable. A broker restart drops the queue even if a message was marked persistent. Declare a durable queue yourself if you need it. This driver will not.

`executeAt` is serialized and then ignored. The publish happens immediately. [Retry](/guide/retry) on this driver republishes immediately too.

## Consume

<Diagram name="drivers-rabbitmq-2" alt="Consume diagram" />

The `basic_consume` arguments are the queue, `$consumerTag` (default `hermes`), `no_local` false, `no_ack` true, exclusive false, nowait false, and the callback. `no_ack` true means the broker settles the delivery when it is sent to the client. The callback unserializes `$rabbitMessage->body` and calls the dispatcher. There is no `basic_ack` and no `basic_nack` in this class.

The consume loop calls `AMQPChannel::wait(null, true)` (non-blocking). When `$refreshInterval` is greater than `0`, it sleeps that many seconds after each wait. The default is `0`, so the loop does not sleep. The example passes `0`. For a quiet queue, pass `1` if you do not want a tight loop:

```php
new LazyRabbitMqDriver($connection, 'hermes_queue', [], 1, 'hermes-worker-1');
```

`$priorities` on `wait()` is unused.

When max items is reached, both loops break, then the driver closes the channel and the connection. A shutdown throws `ShutdownException` before that close. `Dispatcher::handle()` catches it, logs `Exiting hermes dispatcher - shutdown`, and returns.

`MaxItemsTrait`, `ShutdownTrait`, and `SerializerAwareTrait` are on this driver. `SharedFileShutdown` does not need RabbitMQ. `RedisShutdown` still needs a Redis client, which this driver does not have.
