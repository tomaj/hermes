---
title: "FAQ"
description: "Answers to common questions about Hermes: priorities, retries, shutdown, workers, payloads, and alternatives."
---

# FAQ

What the handler does with a failure is decided entirely in `Dispatcher`. The broker is not asked to nack.

<Diagram name="faq-1" alt="A handler returns or throws. Thrown with retries left sends a new message, otherwise the original delivery is already removed" />

## The README passes priority as the third `Message` argument. Why does this guide not?

`Message::__construct` is `(string $type, ?array $payload = null, ?string $messageId = null, ?float $created = null, ?float $executeAt = null, int $retries = 0)`. The third parameter is an id. Priority is the second argument of `Emitter::emit()` and of `Driver::send()`. On PHP 8 an integer in the id position is a `TypeError`.

`examples/redis/emitter.php` passes the priority to `emit()`. Follow the example, not the older README snippet.

## Why did a high priority run after a low one?

`RedisSetDriver` and `PredisSetDriver` poll with `array_reverse` of the insertion order. They do not sort the integers. The default priority `100` is inserted in the constructor, so it is polled last. [Issue #50](https://github.com/tomaj/hermes/issues/50) describes this. [Priority](/guide/priority) shows a `handle([110])` worker as the way to pin one integer.

RabbitMQ and SQS do not implement priority. `setupPriorityQueue()` throws `NotSupportedException`.

## Why was retry instant on RabbitMQ or SQS?

`executeAt` is honored by the two Redis drivers only. The other `send()` methods publish immediately. The retry copy still increments `retries` and still stops after `maxRetry()`.

## I returned `false` and the message disappeared

The driver had already removed it. `false` does not requeue. An exception requeues only when the handler has `canRetry()` and `maxRetry()` methods and `getRetries() < maxRetry()`.

## `canRetry()` returns `false` and it still retries

`Dispatcher` uses `method_exists`, not the return value. Remove the trait, or return `0` from `maxRetry()`.

## There is no `Dispatcher::PRIORITY_HIGH`

The only priority constant is `DEFAULT_PRIORITY = 100`. The comment in `examples/redis/processor.php` that mentions `PRIORITY_HIGH` does not match the class. Pass the integer you registered, for example `Dispatcher::DEFAULT_PRIORITY + 10`.

## `composer test` is not a script

Use `make test`, `composer phpunit`, or `vendor/bin/phpunit`. [Testing](/guide/testing) lists the scripts that `composer.json` actually defines.

## Does Hermes restart the worker?

No. Since 4.0.0 the feature is shutdown. `handle()` returns. Supervisor `autorestart`, systemd `Restart=always`, or a Kubernetes replica starts the process again.

## Will a shutdown key kill the new process too?

Not with `RedisShutdown` or `PredisShutdown`, when the new process starts after the stored timestamp. `shouldShutdown()` returns false if the timestamp is older than the start time or is still in the future. `SharedFileShutdown` returns false when the file is missing or older than the start time. A file timestamp in the future still stops the worker, because the comparison is `filemtime >= start`.

## Where did ZeroMQ go?

Out of this package in 3.0.0, into [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver). It is not in `src/Driver`.

## Which `RabbitMqDriver` do I construct?

`LazyRabbitMqDriver`. The non-lazy `RabbitMqDriver` was removed in 4.0.0.

## Why does `Emitter` log the word Dispatcher?

`Emitter::emit` writes `Dispatcher send message #<id> to driver <class>`. That string is in `src/Emitter.php`. The deprecated `Dispatcher::emit` adds `with priority <n>` to its own string.

## The coverage site used to be the whole GitHub Pages site

The HTML report is now published at [https://tomaj.github.io/hermes/coverage/](https://tomaj.github.io/hermes/coverage/) next to this guide. The CI workflow still uploads the `coverage-report` artifact. The docs workflow copies it into the Pages artifact instead of deploying a second site over the first one.

## How do I stop a message from disappearing?

You cannot, with the built-in drivers, keep the broker copy until `handle()` returns. Redis and Predis `SPOP` first. RabbitMQ consumes with `no_ack` true. SQS is the exception: from 6.0 it calls `deleteMessage` only after the callback returns, so a failed or crashed handler leaves the message for the visibility timeout ([#69](https://github.com/tomaj/hermes/issues/69)). On the other drivers, a crash after the removal and before the handler finishes drops that delivery. A retry copy is published only when the handler throws and still has retries. The practical controls are a short handler, `setMaxProcessItems()` so the process restarts cleanly, and a handler that can run twice safely.

## What happens when the handler throws?

`Dispatcher` catches the exception per handler. It logs an error. If Tracy's debugger is already enabled, it also writes the exception there. It then calls `retryMessage()`. Later handlers for the same type still run. The exception does not by itself stop the worker. See [Retry](/guide/retry).

## How do I run more than one worker?

Start the same CLI script more than once. Supervisor `numprocs`, several systemd units, or a Kubernetes replica count are enough. The workers do not elect a leader. Redis `SPOP` and the brokers give each delivery to one consumer. [Production deployment](/guide/deployment) shows the shape.

## How do I restart workers after a deploy?

Hermes does not reload code inside the running process. Signal shutdown (`SharedFileShutdown`, `RedisShutdown`, or `PredisShutdown`), let `handle()` return, and let the process manager start the new binary. A worker whose start time is after the shutdown timestamp keeps running, which is what you want for the new replica. Delete a stale Redis shutdown key only when you want the next signal to be a new timestamp. Leaving the old key in place does not stop those newer processes.

## How do I test a handler without Redis?

Construct a `Message` and call `handle()` yourself. That is the whole interface. To test registration and dispatch, the test suite uses `Tomaj\Hermes\Test\Driver\DummyDriver`, which is not part of `src/`. `unregisterHandler()` and `unregisterAllHandlers()` exist so a test can reuse one dispatcher.

## Will a long-running worker leak memory?

Hermes stores the handler objects and a processed-item counter. It does not pool your HTTP clients, file handles, or ORM identity maps. Those grow if your handler keeps them. `setMaxProcessItems()` makes `wait()` return after N messages so the process manager can start a fresh process. That is the recycle switch. There is no other memory limit in the library.

## Are messages FIFO?

No. Live Redis and Predis queues are sets. `SPOP` returns one random member. Two messages with the same priority have no order. Across priorities, `wait()` walks queues in reverse insertion order, not numeric order. [Issue #50](https://github.com/tomaj/hermes/issues/50) is that behavior. RabbitMQ and SQS order is whatever those brokers do with a single queue. Hermes does not set a FIFO queue or a message group.

## Can the same work run twice?

A normal success does not republish. A retry does, and it reuses the same message id with `retries + 1`. If the handler already performed the side effect and then threw, the next attempt does it again. If the process dies after the side effect and after the broker removal, the attempt is not repeated, because nothing is left to retry. Treat the message id as an idempotency key if the work must not happen twice.

## How big can the payload be?

Hermes JSON-encodes the payload and does not enforce a size. The payload must be an array or `null`. Resources and open connections will not survive `json_encode()`. The broker can still reject the body. SQS, for example, has its own limit. `send()` runs in the web process, so a huge payload slows that request.

## How do I monitor Hermes?

There is no metrics port and no health URL. The PSR-3 lines are the built-in signal: send, start, end, handler error, and the shutdown notice. `processed()` is an in-process counter. The process manager knows whether `worker.php` is running. Anything else is a log scraper or a metric you increment inside the handler.

## Should I use Tracy or a PSR-3 logger?

Both, for different things. The logger is the normal trail and is injected into handlers that have `setLogger()`. Tracy receives the exception only when `Debugger::isEnabled()` is already true. Hermes never calls `Debugger::enable()`. With a null logger, the info and error lines are skipped. Tracy can still record the exception if you enabled it yourself.

## How does this compare with Symfony Messenger or Laravel queues?

Those frameworks ack after the handler, store failed jobs, and ship a consumer command. Hermes removes the delivery before `handle()` returns, has no failed-job table, and has no `bin/console` command. You write `worker.php`. Delay is `executeAt` on the Redis drivers only. Retry is `RetryTrait` plus the formula in `Dispatcher::nextRetry()`, not a transport retry policy. Middleware, stamps, and a separate failure transport are not part of this library. Use Hermes when you want that smaller loop. Use Messenger or Laravel queues when you want ack-after-success and their retry stacks.
