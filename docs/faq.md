---
title: "FAQ"
description: "Answers to common questions about Hermes: priorities, retries, shutdown, workers, payloads, and alternatives."
---

# FAQ

What the handler does with a failure is decided entirely in `Dispatcher`. The broker is not asked to nack.

<Diagram name="faq-1" alt="A handler returns or throws. Thrown with retries left sends a new message, otherwise the original delivery is already removed" />

## Is priority an argument of `Message` or of `emit()`?

Of `emit()`. `Message::__construct` is `(string $type, ?array $payload = null, ?string $messageId = null, ?float $created = null, ?float $executeAt = null, int $retries = 0)`. The third parameter is an id. Priority is the second argument of `Emitter::emit()` and of `Driver::send()`.

An integer in the id position does not set a priority. With `declare(strict_types=1)` it is a `TypeError`. Without it, PHP turns it into the id string, for example `"110"`, and the message goes to the default priority. README examples before 6.0 made this mistake.

## Why did a high priority run after a low one?

From 6.0 `RedisSetDriver` and `PredisSetDriver` poll queues by priority number, highest first. Up to 5.x they polled in reverse registration order, so the default priority `100`, registered in the constructor, was polled last ([issue #50](https://github.com/tomaj/hermes/issues/50)). On 5.x, [Priority](/guide/priority) shows a `handle([110])` worker as the way to pin one integer.

RabbitMQ, SQS and Kafka do not implement priority. `setupPriorityQueue()` throws `NotSupportedException`.

## Why was retry instant on RabbitMQ or SQS?

`executeAt` is honored by the two Redis drivers and by `FileSystemDriver`. RabbitMQ, SQS and Kafka publish immediately. The retry copy still increments `retries` and still stops after `maxRetry()`.

## I returned `false` and the message disappeared

The return value is not checked: the Redis drivers removed the message before the handler ran, and RabbitMQ, SQS, Kafka and the filesystem driver acknowledge or delete it once `handle()` returns, `true` or `false`. `false` does not requeue. An exception sends a retry copy only when the handler implements `RetryableHandlerInterface`, `getRetries() < maxRetry()` and `canRetry()` returns `true`. A 5.x handler with `canRetry()` and `maxRetry()` but without the interface is retried while `getRetries() < maxRetry()` ([deprecated](/guide/retry#handlers-without-the-interface-deprecated)).

## `canRetry()` returns `false` and it still retries

`Dispatcher` calls `canRetry($message, $exception)` and skips the retry when it returns `false` only for a handler that implements `RetryableHandlerInterface` ([#107](https://github.com/tomaj/hermes/issues/107)). Without the interface the handler follows the deprecated 5.x rule, which never calls `canRetry()`: add `implements RetryableHandlerInterface` and the full `canRetry()` signature. See [Retry](/guide/retry#handlers-without-the-interface-deprecated).

## My log has a deprecation about `canRetry()` and `maxRetry()` after the upgrade

The handler named in the message has `canRetry()` and `maxRetry()` (its own or from `RetryTrait`) but does not implement `RetryableHandlerInterface`. 6.0 still retries it as 5.x did, without calling `canRetry()`, and reports it once per class. 7.0 stops retrying it. Add `implements RetryableHandlerInterface` and give `canRetry()` the `(MessageInterface $message, Throwable $exception)` parameters. See [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md#retries-follow-retryablehandlerinterface-and-canretry).

## There is no `Dispatcher::PRIORITY_HIGH`

The only priority constant is `DEFAULT_PRIORITY = 100`. Pass the integer you registered, for example `Dispatcher::DEFAULT_PRIORITY + 10`.

## How do I run the tests?

`composer test` runs PHPUnit, and `composer check` runs code style, PHPStan, Rector and the tests. [Testing](/guide/testing) lists every script that `composer.json` defines.

## Does Hermes restart the worker?

No. Since 4.0.0 the feature is shutdown. `handle()` returns. Supervisor `autorestart`, systemd `Restart=always`, or a Kubernetes replica starts the process again.

## Will a shutdown key kill the new process too?

No. All three shutdowns store the time with microseconds, and `shouldShutdown()` returns true only when that time is later than the worker's start time and not in the future. A process that starts after the shutdown keeps running, even in the same second ([#115](https://github.com/tomaj/hermes/issues/115)). For `SharedFileShutdown`, a missing file also means keep running.

## My shutdown class fails with "must be compatible with ShutdownInterface"

6.0 types `shouldShutdown()` and `shutdown()` with `DateTimeInterface` instead of `DateTime` ([#108](https://github.com/tomaj/hermes/issues/108)). Change both parameter types in your class to `DateTimeInterface`. The start time is a `DateTimeImmutable`. See [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md).

## Where did ZeroMQ go?

Out of this package in 3.0.0, into [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver). It is not in `src/Driver`. It requires `tomaj/hermes` `^2.1`, so it does not install with 6.x; implement `DriverInterface` yourself (see [Extending Hermes](/guide/extending)).

## Which `RabbitMqDriver` do I construct?

`LazyRabbitMqDriver`. The non-lazy `RabbitMqDriver` was removed in 4.0.0.

## The coverage site used to be the whole GitHub Pages site

The HTML report is now published at [https://tomaj.github.io/hermes/coverage/](https://tomaj.github.io/hermes/coverage/) next to this guide. The CI workflow still uploads the `coverage-report` artifact. The docs workflow copies it into the Pages artifact instead of deploying a second site over the first one.

## How do I stop a message from disappearing?

You cannot, with the Redis drivers, keep the broker copy until `handle()` returns: Redis and Predis `SPOP` first, so a crash after that removal and before the handler finishes drops that delivery. From 6.0 RabbitMQ acknowledges only after the callback ([#70](https://github.com/tomaj/hermes/issues/70)), SQS calls `deleteMessage` only after the callback returns ([#69](https://github.com/tomaj/hermes/issues/69)), Kafka commits the offset only after the callback, and `FileSystemDriver` deletes the file only after the callback, so a failed or crashed handler leaves the message to be redelivered. A retry copy is published only when the handler throws and still has retries. The practical controls are a short handler, `setMaxProcessItems()` so the process restarts cleanly, and a handler that can run twice safely.

## What happens when the handler throws?

`Dispatcher` catches any `Throwable` per handler, including PHP `Error`s such as `TypeError`. It logs an error. If Tracy is installed and its debugger is already enabled, it also writes the exception there. It then calls `retryMessage()`. Later handlers for the same type still run. The exception does not by itself stop the worker. See [Retry](/guide/retry).

## Does a worker stop cleanly on SIGTERM or Ctrl+C?

Yes, when `ext-pcntl` is loaded. It finishes the current message, logs `Exiting hermes dispatcher - received SIGTERM` and returns from `handle()`. Give the process manager a stop timeout longer than one message, because it sends `SIGKILL` after that. See [Shutdown](/guide/shutdown#sigterm-and-sigint).

## How do I run more than one worker?

Start the same CLI script more than once. Supervisor `numprocs`, several systemd units, or a Kubernetes replica count are enough. The workers do not elect a leader. Redis `SPOP`, the brokers, Kafka consumer groups, and `FileSystemDriver`'s atomic `rename()` give each delivery to one consumer. [Production deployment](/guide/deployment) shows the shape.

## How do I restart workers after a deploy?

Hermes does not reload code inside the running process. Signal shutdown (`SharedFileShutdown`, `RedisShutdown`, or `PredisShutdown`), let `handle()` return, and let the process manager start the new binary. A worker whose start time is after the shutdown timestamp keeps running, which is what you want for the new replica. Delete a stale Redis shutdown key only when you want the next signal to be a new timestamp. Leaving the old key in place does not stop those newer processes.

## How do I test a handler without Redis?

Construct a `Message` and call `handle()` yourself. That is the whole interface. To test registration and dispatch, the test suite uses `Tomaj\Hermes\Test\Driver\DummyDriver`, which is not part of `src/`. `unregisterHandler()` and `unregisterAllHandlers()` exist so a test can reuse one dispatcher.

## Will a long-running worker leak memory?

Hermes stores the handler objects and a processed-item counter. It does not pool your HTTP clients, file handles, or ORM identity maps. Those grow if your handler keeps them. `setMaxProcessItems()` makes `wait()` return after N messages so the process manager can start a fresh process. That is the recycle switch. There is no other memory limit in the library.

## Are messages FIFO?

Not on Redis or Predis: live queues are sets, and `SPOP` returns one random member. Two messages with the same priority have no order. Across priorities, `wait()` walks queues from the highest number down (5.x used reverse insertion order, [issue #50](https://github.com/tomaj/hermes/issues/50)). `FileSystemDriver` is FIFO within each priority folder. RabbitMQ, SQS and Kafka order is whatever those brokers do with a single queue (Kafka: one partition is ordered). Hermes does not set a FIFO SQS queue or a message group.

## My handler does not find the row I have just inserted

The message was sent before the transaction committed, and the worker was faster than the commit. Wrap the emitter in `BufferedEmitter` and flush it from your commit hook; see [Emitting after a database commit](/guide/buffered-emitter).

## Can the same work run twice?

A normal success does not republish. A retry does, and it reuses the same message id with `retries + 1`. If the handler already performed the side effect and then threw, the next attempt does it again. If the process dies after the side effect and after the broker removal, the attempt is not repeated, because nothing is left to retry. Treat the message id as an idempotency key if the work must not happen twice.

## How big can the payload be?

Hermes JSON-encodes the payload and does not enforce a size. The payload must be an array or `null`. Resources and open connections will not survive `json_encode()`. The broker can still reject the body. SQS, for example, has its own limit. `send()` runs in the web process, so a huge payload slows that request.

## How do I monitor Hermes?

There is no metrics port and no health URL. The PSR-3 lines are the built-in signal: send, start, end, handler error, and the shutdown notice. `processed()` is an in-process counter. The process manager knows whether `worker.php` is running. Anything else is a log scraper or a metric you increment inside the handler.

## Should I use Tracy or a PSR-3 logger?

Both, for different things, and Tracy is optional (`composer require tracy/tracy`). The logger is the normal trail and is injected into handlers that have `setLogger()`. Tracy receives the exception only when `Debugger::isEnabled()` is already true. Hermes never calls `Debugger::enable()`. With a null logger, the info and error lines are skipped. Tracy can still record the exception if you enabled it yourself.

## How does this compare with Symfony Messenger or Laravel queues?

Those frameworks ack after the handler, store failed jobs, and ship a consumer command. The Redis drivers remove the delivery before `handle()` returns; RabbitMQ, SQS, Kafka and `FileSystemDriver` remove it only after. Hermes has no failed-job table and no `bin/console` command. You write `worker.php`. Delay is `executeAt` on the Redis drivers and on `FileSystemDriver`. Retry is `RetryTrait` plus the formula in `Dispatcher::nextRetry()`, not a transport retry policy. Middleware, stamps, and a separate failure transport are not part of this library. Use Hermes when you want that smaller loop. Use Messenger or Laravel queues when you want ack-after-success and their retry stacks.
