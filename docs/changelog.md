# Changelog

Notable changes from [CHANGELOG.md](https://github.com/tomaj/hermes/blob/master/CHANGELOG.md) and the [GitHub releases](https://github.com/tomaj/hermes/releases). The 5.0.0 notes are still under the `Unreleased` heading in `CHANGELOG.md`. The [5.0.0 release](https://github.com/tomaj/hermes/releases/tag/5.0.0) publishes those same notes.

## 5.0.0

Minimum PHP is 7.4. PHP 7.2 and 7.3 left the CI matrix. The matrix includes 8.0, 8.1, 8.2, and 8.3.

`Message`, `Dispatcher`, and `MessageSerializer` use typed properties and stricter checks. JSON serialization failures throw `SerializeException`. Implicitly nullable parameters were removed.

PHPStan runs with strict rules. Coverage is local HTML plus Clover, and CI publishes the HTML report. This documentation site serves that report at `/coverage/` instead of replacing the Pages root.

## 4.2.0

`Dispatcher` can unregister handlers: `unregisterHandler()` and `unregisterAllHandlers()`. The changelog marks the interface change as breaking.

## 4.1.0

`RedisSetDriver` schedule handling was updated for the scheduled set. The current `wait()` path is documented on [Delayed execution](/guide/delayed): `zRangeByScore`, then `sPop` on the schedule key.

## 4.0.1

Predis retry scheduling was fixed ([issue #48](https://github.com/tomaj/hermes/issues/48)). `PredisSetDriver::send()` writes the sorted set as `[$serialized => $executeAt]`.

## 4.0.0

- `Restart*` was renamed to `Shutdown*`. Hermes stops its own loop. A process manager starts it again.
- Deprecated `RabbitMqDriver` was removed. Use `LazyRabbitMqDriver`.
- `RedisSetDriver` is ext-redis only. Predis code must use `PredisSetDriver`.
- `SharedFileShutdown` calls `clearstatcache()` before it reads the file.

## 3.1.0

Soft restart support on the drivers of that release, a consumer tag on `LazyRabbitMqDriver`, and max-items plus restart support for the lazy RabbitMQ driver and the SQS driver.

## 3.0.0

- `RedisRestart` (now `RedisShutdown`) stops a worker through a Redis key.
- `RestartInterface::restart()` was added. The 4.0 rename turned that idea into `ShutdownInterface::shutdown()`.
- The ZeroMQ driver moved to [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver).
- PHP 7.1 was dropped.

## 2.2.0

`registerHandlers()` registers several handlers for one type. RabbitMQ drivers gained an ack-related retry fix in that release. The current `LazyRabbitMqDriver` consumes with `no_ack = true`. [RabbitMQ](/guide/drivers/rabbitmq) describes that call.

## 2.1.0

Handlers can retry. `DispatcherInterface::handle()` was added to the interface.

## 2.0.0

Messages gained `executeAt`. `Emitter` replaced `Dispatcher::emit()`, which is still present and deprecated. PHP 5.4 was dropped.

## 1.x and earlier

1.2.0 updated the suggested AWS SDK major. 1.1.0 added Amazon SQS. 1.0.0 was the first stable release and added ack on the RabbitMQ driver of that time. 0.4.0 added the lazy RabbitMQ driver. 0.3.0 added graceful restart and Tracy exception logging. 0.2.0 changed how handler return values are treated. 0.1.0 was the initial release with two drivers.
