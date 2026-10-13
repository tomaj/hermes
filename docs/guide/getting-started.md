---
title: "Getting started"
description: "Install Hermes 5.x, emit a message from a web request, and run a PHP worker that handles it."
---

# Getting started

Hermes 5.x is a PHP library. There is no Hermes binary. A web request constructs a `Message` and passes it to `Emitter`. A separate PHP CLI process constructs a `Dispatcher`, registers handlers, and calls `handle()`, which blocks.

<Diagram name="getting-started-1" alt="Sequence: the app emits a message, the emitter sends it to the driver, and the dispatcher waits, receives it, and calls the handler" />

The package constraint in `composer.json` is `php: ^8.4`. CI runs PHP 8.4, PHP 8.4 with the lowest dependencies, and PHP 8.5.

## Install

```bash
composer require tomaj/hermes
```

That pulls in `ext-json`, `ramsey/uuid` (`^3 || ^4`), `psr/log` (`^1 || ^2 || ^3`), and `tracy/tracy` (`^2.0`). You still pick a queue client:

| Driver | What you install |
| --- | --- |
| `RedisSetDriver` | The [phpredis](https://github.com/phpredis/phpredis) extension (`ext-redis`). Composer does not install the extension. |
| `PredisSetDriver` | `composer require predis/predis` (`^1.1` in this package's dev requirement) |
| `LazyRabbitMqDriver` | `composer require php-amqplib/php-amqplib` |
| `AmazonSqsDriver` | `composer require aws/aws-sdk-php` |

Logging is optional. Any PSR-3 logger works. [Monolog](https://github.com/Seldaek/monolog) is the usual choice and is listed under `suggest`, not `require`.

## Quickstart

This is the Redis extension driver, matching `examples/redis`. The third constructor argument of `Message` is a message id, not a priority. Priority is the second argument of `emit()`.

```php
<?php

declare(strict_types=1);

use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$driver = new RedisSetDriver($redis);
$emitter = new Emitter($driver);

$emitter->emit(new Message('send-email', [
    'to' => 'test@test.com',
    'subject' => 'Testing hermes email',
    'message' => 'Hello from hermes!',
]));
```

`RedisSetDriver` registers the key `hermes` at `Dispatcher::DEFAULT_PRIORITY` (`100`). `emit()` without a second argument uses that same constant.

The worker lives in another file and is started with `php worker.php`. `EchoHandler` is included in the library. It prints the id, the type, and `json_encode()` of the payload, then returns `true`.

```php
<?php

declare(strict_types=1);

use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Handler\EchoHandler;

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$driver = new RedisSetDriver($redis);
$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('send-email', new EchoHandler());

$dispatcher->handle();
```

`handle()` does not return until the driver stops the loop (shutdown, max items, or a driver error). Do not call it from a web request. The docblock on `Dispatcher::handle()` says the same thing.

<img class="theme-img only-light" src="/illustrations/terminal-light.svg" alt="Terminal showing EchoHandler output" />
<img class="theme-img only-dark" src="/illustrations/terminal-dark.svg" alt="" />

Stdout from `EchoHandler` looks like this:

```text
Received message: #6f1c0a2e-9b44-4d1a-8c33-0a9e5d77b214 (type send-email)
Payload: {"to":"test@test.com","subject":"Testing hermes email","message":"Hello from hermes!"}
```

Those lines are the only output unless you pass a logger. See [Logging](/guide/logging).

## A handler of your own

`HandlerInterface` has one method. Return `true` when the work succeeded. Return `false` to mark that call as failed. Throw to mark it as failed and, when the handler uses `RetryTrait`, to schedule another attempt.

```php
<?php

declare(strict_types=1);

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class SendEmailHandler implements HandlerInterface
{
    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if ($payload === null) {
            return false;
        }

        mail($payload['to'], $payload['subject'], $payload['message']);

        return true;
    }
}
```

Register it with the same string you used as the message type:

```php
$dispatcher->registerHandler('send-email', new SendEmailHandler());
```

Returning `false` does not put the message back. The Redis driver has already removed it with `SPOP`. [Retry](/guide/retry) explains the only built-in second chance.

## What to read next

- [Concepts](/guide/concepts) for the five types you actually call.
- [Redis](/guide/drivers/redis) or [Predis](/guide/drivers/predis) for the connection you have.
- [Production deployment](/guide/deployment) when the worker should survive a deploy.

## FAQ

### Does Hermes need a server or daemon of its own?

No. Hermes is a PHP library. Your web code emits messages, and a PHP CLI script that you write runs the worker with `Dispatcher::handle()`. The only shared piece is the broker: Redis, RabbitMQ, or SQS.

### Which driver should I start with?

Use `PredisSetDriver` if you cannot install a PHP extension, or `RedisSetDriver` if you already have phpredis. Both support priorities. Only Predis moves delayed messages and retries back to the queue reliably in 5.x. See the [driver comparison](/guide/drivers/).

### Can I call `handle()` from a web request?

No. `handle()` blocks until shutdown, max items, or a driver error. Run it in a separate CLI process under a process manager. See [Production deployment](/guide/deployment).

### What should a handler return?

`true` when the work succeeded and `false` when it failed for good. Throw an exception when a later attempt could succeed: with `RetryTrait` the dispatcher then sends a retry copy. See [Retry](/guide/retry).

### Is there a complete project I can copy?

Yes. The [welcome email tutorial](/examples/welcome-email) builds an app from an empty folder, with Redis and Mailpit in Docker Compose, a real email handler, retries, a test, and Supervisor.
