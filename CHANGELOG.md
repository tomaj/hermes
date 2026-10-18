# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

### Added

* Integration tests run against Redis, RabbitMQ and moto (SQS) in CI. `HERMES_REQUIRE_SERVICES=1` turns a skipped integration test into a failure; `compose.yaml` and `composer test:integration` run them locally. The README shows Coveralls coverage ([#74](https://github.com/tomaj/hermes/issues/74))
* `Dispatcher` takes an optional fourth argument `?Random\Randomizer $randomizer` for the retry jitter. Pass one with a seeded engine for reproducible retry times. The default `new Randomizer()` replaces the global `rand()`, so `mt_srand()` no longer makes the retry times reproducible: pass a seeded `Randomizer` instead ([#82](https://github.com/tomaj/hermes/issues/82)).
* Enhanced PHPStan configuration with strict rules and additional type checking
* Added `phpstan/phpstan-strict-rules` extension for improved code quality analysis
* PHPStan also runs with bleeding edge, `phpstan/phpstan-deprecation-rules`, `phpstan/phpstan-phpunit`, `checkUninitializedProperties` and `reportUnmatchedIgnoredErrors`, without a baseline or ignored errors ([#85](https://github.com/tomaj/hermes/issues/85))
* Improved type safety with PHP 7.4+ typed properties in core classes
* Added comprehensive GitHub Actions workflows with PHP 7.4+ support
* Added security audit workflow with weekly vulnerability scanning
* Added composer validation and dependency caching in CI
* Added local code coverage reporting with HTML and Clover XML output
* Added GitHub Pages deployment for coverage reports
* Infection fails below 85% MSI and 90% covered code MSI, up from 65% for both. New tests cover the logger context arrays, every `MessageSerializer` field, the exact Redis commands of `PredisSetDriver` and `RedisSetDriver`, SQS queue attributes, and an SQS receive with no messages ([#86](https://github.com/tomaj/hermes/issues/86)).
* Rector configuration (`rector.php`) with the PHP 8.4 set and the dead code, code quality, type declaration and early return sets. `composer rector` reports changes and `composer check` runs it; `composer rector-fix` applies them. `src` and `tests` follow it ([#84](https://github.com/tomaj/hermes/issues/84)).
* PHPUnit fails the run on notices, deprecations, PHPUnit notices, deprecations and warnings, and an empty test suite, besides risky tests and warnings. A mock without expectations, which PHPUnit reports as a notice, fails the build; use `createStub()` instead. Deprecations that one dependency triggers in another dependency are ignored, so the run with the lowest dependencies passes ([#109](https://github.com/tomaj/hermes/issues/109)).
* With `ext-pcntl`, `Dispatcher::handle()` stops gracefully on `SIGTERM` and `SIGINT`: it finishes the current message, logs `Exiting hermes dispatcher - received SIGTERM` and returns. `ext-pcntl` is suggested in `composer.json` ([#116](https://github.com/tomaj/hermes/issues/116))

### Changed

* **BREAKING CHANGE**: `Dispatcher` retries a failed handler only when it implements the new `RetryableHandlerInterface` or uses `RetryTrait`, and calls `canRetry($message, $exception)`: `false` skips the retry. 5.x only checked that `canRetry()` and `maxRetry()` existed and never called `canRetry()`. Handlers with those methods but neither the interface nor the trait are no longer retried. `RetryTrait::canRetry()` takes the message and the exception ([#107](https://github.com/tomaj/hermes/issues/107)).
* **BREAKING CHANGE**: `Emitter::emit()` logs `Emitter send message #<id> to driver <class>` instead of `Dispatcher send message …`, so emitted messages no longer look as if the dispatcher sent them. Update log searches and alerts that match the old text. The deprecated `Dispatcher::emit()` keeps its text ([#111](https://github.com/tomaj/hermes/issues/111)).
* Constructor property promotion, `readonly` for state that never changes, and native property types across `src/`. Docblocks that repeated native signatures and `{@inheritdoc}` blocks were removed; tags that carry information beyond the native type (`@return $this`, array value types, `@throws`) stay. Constructor parameter types, names, order and defaults are unchanged and only private members changed, so the public API is the same. Code that wrote those private properties through reflection or `Closure::bind()` after construction now gets an `Error`, because they are `readonly`. PHP `serialize()` and `var_export()` list `Message` properties in a different order; strings serialized by 5.x still unserialize ([#78](https://github.com/tomaj/hermes/issues/78), [#79](https://github.com/tomaj/hermes/issues/79)).
* **BREAKING CHANGE**: `LazyRabbitMqDriver` acknowledges messages manually: `basic_ack` after the callback, `basic_nack` with requeue when it throws, `basic_nack` without requeue when the body cannot be unserialized, prefetch 1. It consumed with `no_ack = true`, which lost the message when the worker crashed ([#70](https://github.com/tomaj/hermes/issues/70)).
* **BREAKING CHANGE**: `LazyRabbitMqDriver` declares a durable queue and publishes persistent messages (`delivery_mode` 2) by default. A queue created by 5.x is non-durable and RabbitMQ rejects the new declaration with `PRECONDITION_FAILED`: delete and recreate the queue, or pass the new `$durable = false` constructor argument ([#70](https://github.com/tomaj/hermes/issues/70)).
* `LazyRabbitMqDriver` counts handled messages, so `setMaxProcessItems()` works with it.
* `LazyRabbitMqDriver` accepts any `PhpAmqpLib\Connection\AbstractConnection`, not only `AMQPLazyConnection`, which php-amqplib deprecated. Pass a lazy connection, for example from `AMQPConnectionFactory` with `AMQPConnectionConfig::setIsLazy(true)`. Existing code that passes `AMQPLazyConnection` keeps working. The driver checks for active consumers with `AMQPChannel::is_consuming()` instead of the internal `$callbacks` property.
* `ShutdownTrait` initializes its properties to `null`, so a driver that never gets a shutdown has no uninitialized properties.
* **BREAKING CHANGE**: `PredisSetDriver` and `PredisShutdown` now support `predis/predis` `^2.3 || ^3.0`. Predis 1.x and 2.0 to 2.2 are no longer tested: they emit deprecations on PHP 8.4. Upgrade Predis together with Hermes.
* `PredisSetDriver::wait()` passes the schedule bound to `zrangebyscore` as a string with microseconds, as Predis 2 and 3 expect.
* Use PHP 8.4 idioms in the source: `::class` on objects instead of `get_class()`, `!== []` instead of `count() > 0`, `??=` for the default shutdown time, `array_map()` with a typed arrow function for SQS queue attributes, and an early return in the retry path. Rector's PHP 8.4 set reports no changes in `src/` ([#83](https://github.com/tomaj/hermes/issues/83)).
* `Message`, `Emitter`, `SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` are `readonly` classes. All their properties were already `readonly`; creating a dynamic property on them now throws an `Error` instead of a deprecation ([#83](https://github.com/tomaj/hermes/issues/83)).
* `LazyRabbitMqDriver::setupPriorityQueue()` and `AmazonSqsDriver::setupPriorityQueue()` declare the `never` return type, because they always throw `NotSupportedException`. The narrower type stays compatible with `DriverInterface`, and the classes are final, so no subclass can conflict. Static analysis now treats code after a call on these concrete types as unreachable: PHPStan reports it ([#83](https://github.com/tomaj/hermes/issues/83)).
* **BREAKING CHANGE**: Raised the minimum PHP version to 8.4. PHP 7.4 and 8.0 through 8.3 are no longer supported.
* `Dispatcher::DEFAULT_PRIORITY` is a typed class constant (`public const int`). `Dispatcher` is final, so nothing can redeclare it. Priorities stay plain integers; the reasons are in [UPGRADE-6.0.md](UPGRADE-6.0.md) ([#80](https://github.com/tomaj/hermes/issues/80)).
* `ramsey/uuid` now requires `^4.7` and `tracy/tracy` now requires `^2.10.10`, so both install on PHP 8.4 and 8.5.
* **BREAKING CHANGE**: `psr/log` now requires `^2 || ^3`; 1.x has no native types. `ext-json` is no longer listed, because PHP 8 always has it. `composer.json` fixes the `suggest` texts, drops the duplicate `cs` script, adds `composer test` and `composer check` (code style, PHPStan, tests), runs `composer phpunit` without coverage so it works without Xdebug or PCOV, sets `XDEBUG_MODE` for `composer coverage` and `composer infection` with `@putenv` so they also run on Windows, and sorts and normalizes the file ([#87](https://github.com/tomaj/hermes/issues/87)).
* **BREAKING CHANGE**: every concrete class in `src/` is `final` (`HermesException` stays open). Extend Hermes through its interfaces; [UPGRADE-6.0.md](UPGRADE-6.0.md) shows how. Methods that implement an interface carry `#[\Override]`, and PHPStan checks for it ([#81](https://github.com/tomaj/hermes/issues/81)).
* **BREAKING CHANGE**: Updated minimum PHP version requirement to PHP 7.4+ with full PHP 8.0+ support
* **BREAKING CHANGE**: Removed PHP 7.2 and 7.3 support from CI/CD workflows
* Enhanced `Message` class with strict typing and improved type annotations
* Enhanced `Dispatcher` class with typed properties and better type safety
* Enhanced `MessageSerializer` class with stricter validation and error handling
* Improved PHPStan analysis with strict comparison checks and function type hints
* Updated composer dependencies to support PHP 8.0+ while maintaining backward compatibility
* Modernized codebase with PHP 7.4+ features (typed properties, arrow functions, null coalescing)
* Updated GitHub Actions to latest versions for better security and performance
* Expanded CI test matrix to include PHP 8.1, 8.2, and 8.3
* Updated .gitignore to exclude log files and cache directory
* Removed Scrutinizer Code Coverage integration in favor of local reporting
* Enabled Xdebug coverage mode in PHPUnit workflow for accurate coverage metrics
* Updated test coverage check to use `johanvanhelden/gha-clover-test-coverage-check@v1`
* **BREAKING CHANGE**: `AmazonSqsDriver` deletes a message only after it was handled, not right after `receiveMessage`. A worker crash or an exception from the callback leaves the message in SQS for the visibility timeout or a dead-letter queue. Delivery is at least once, so set `VisibilityTimeout` above your slowest handler ([#69](https://github.com/tomaj/hermes/issues/69)).
* Coverage reports are now automatically published to GitHub Pages after successful test runs
* **BREAKING CHANGE**: `ShutdownInterface::shouldShutdown()` and `shutdown()` take `DateTimeInterface` instead of `DateTime`. `Dispatcher` and `ShutdownTrait` keep the worker start time as a `DateTimeImmutable`, so a shutdown can no longer shift the dispatcher's own start time by modifying it. Callers can pass `DateTime` or `DateTimeImmutable`; custom implementations must change their parameter types ([#108](https://github.com/tomaj/hermes/issues/108)).

### Removed

* `.scrutinizer.yml`, `coverage.sh` and the root `Makefile`. Use `composer phpunit` and `composer coverage` instead. The Composer dist archive now contains only `src/`, the license, README, CHANGELOG, UPGRADE-6.0.md and `composer.json` ([#88](https://github.com/tomaj/hermes/issues/88)).
* The private `AmazonSqsDriver::$sleepInterval` and the empty-queue sleep that used it. Nothing could set it, so the branch never ran; long polling (`WaitTimeSeconds`) paces the loop as before ([#110](https://github.com/tomaj/hermes/issues/110)).

### Fixed

* **BREAKING CHANGE**: `Dispatcher` now catches any `Throwable` thrown by a handler, not only `Exception`. A PHP `Error` (for example `TypeError`) is logged, counts as a failed message and follows the normal retry rules instead of killing the worker ([#112](https://github.com/tomaj/hermes/issues/112))
* `SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` store the shutdown time with microseconds and stop only workers that started before it. A worker started in the same second as `hermes:restart` no longer exits at once. `SharedFileShutdown` ignores a future time, like the Redis shutdowns. See UPGRADE-6.0.md ([#115](https://github.com/tomaj/hermes/issues/115)).
* Fixed strict comparison issues in `Message` constructor
* Fixed boolean condition checks for better PHP 8+ compatibility
* `RedisSetDriver` delivers delayed messages and retries again. Due entries are removed from the schedule sorted set with `ZREM`, and only the worker that removed an entry re-sends it. It used `SPOP`, which does not work on a sorted set, so they stayed in the schedule forever ([#95](https://github.com/tomaj/hermes/issues/95)).
* Added proper return type hints to anonymous functions
* Improved JSON serialization error handling in `MessageSerializer`
* Removed implicitly nullable parameters for better type safety
* **BREAKING CHANGE**: `RedisSetDriver` and `PredisSetDriver` poll priority queues by priority number, highest first. They polled in reverse registration order, so the default priority 100 (registered in the constructor) always came last ([#50](https://github.com/tomaj/hermes/issues/50)). Workers that relied on the old order now process queues in a different order.
* **BREAKING CHANGE**: an `Exception` from the driver in `Dispatcher::handle()` is now logged at `error` (`Exiting hermes dispatcher - driver error: ...`) and rethrown, so the worker exits non-zero instead of returning silently with status 0 ([#113](https://github.com/tomaj/hermes/issues/113))


## 4.2.0 - 2024-04-29

### Added

* **BREAKING CHANGE**: changed interface for Dispatcher - added ability to unregister handlers


## 4.1.0 - 2023-12-17

### Changed

- RedisSetDriver - add atomicity to scheduled set


## 4.0.1 - 2021-11-23

### Changed

* Fixed Predis driver when retry is being scheduled [#48](https://github.com/tomaj/hermes/issues/48)


## 4.0.0 - 2021-02-02

### Changed

* **BREAKING CHANGE**: Renamed all **Restart** to **Shutdown**
* **BREAKING CHANGE**: Removed deprecated *RabbitMqDriver**. You can use LazyRabbitMqDriver
* **BREAKING CHANGE**: Splitted **RedisSetDriver** to two implementations based on how it is interacting with redis. For using Redis php extension you can use old **RedisSetDriver**, for using predis package you have to use **PredisSetDriver**
* Added clearstatcache() into SharedFileShutdown



## 3.1.0 - 2020-10-22

### Changed

* Added support for *soft restart* to all drivers
* Added `consumer tag` to LazyRabbitMq driver for consumer
* Added support for _max items_ and _restart_ for LazyRabbitMq Driver
* updated restart policy for AmazonSQS Driver  


## 3.0.1 - 2020-10-16

### Changed

* Fixed `RedisRestart::restart()` response for Predis instance. `\Predis\Client::set()` returns object _(with 'OK' payload)_ instead of bool.
* Deprecated `RabbitMqDriver` (will be removed in 4.0.0) - use `LazyRabbitMqDriver` instead
* Fixed error while parsing message with invalid UTF8 character

## 3.0.0 - 2020-10-13

### Added

* `RedisRestart` - implementation of `RestartInterface` allowing graceful shutdown of Hermes through Redis entry.
* **BREAKING CHANGE**: Added `RestartInterface::restart()` method to initiate Hermes restart without knowing the requirements of used `RestartInterface` implementation. _Updated all related tests._
* **BREAKING CHANGE**: Removed support for ZeroMQ - driver moved into [separated package](https://github.com/tomaj/hermes-zmq-driver)
* Upgraded phpunit and tests
* **BREAKING CHANGE** Drop support for php 7.1

## 2.2.0 - 2019-07-12

### Added

* Ability to register multiple handlers at once for one key (`registerHandlers` in `DispatcherInterface`)
* Fixed loss of messages when the handler crashes and mechanism of retries for RabbitMQ Drivers 

## 2.1.0 - 2019-07-06

### Added

* Added retry to handlers

#### Added

* Added missing handle() method to DispatcherInterface

## 2.0.0 - 2018-08-14

### Added

* Message now support scheduled parameter - Driver needs to support this behaviour.
* Type hints

### Changed

* Dropped support for php 5.4
* Deprecated emit() in Disapatcher - introduced Emitter

## 1.2.0 - 2016-09-26

### Updated

* Amazon aws library updated to version 3 in composer - still works with v2 but you have to initialize Sqs client in v2 style

## 1.1.0 - 2016-09-05

### Added

* Amazon SQS driver

## 1.0.0 - 2016-09-02

### Added

* First stable version
* Added ACK to rabbitmq driver

## 0.4.0 - 2016-04-26

### Added

* Added RabbitMQ Lazy driver

## 0.3.0 - 2016-03-23

### Added

* Added possibility to gracefull restart worker with RestartInterface
* Added Tracy debugger log when error occured

## 0.2.0 - 2015-10-30

### Changed

* Handling responses from handlers.
* Tests structure refactored

## 0.1.0 - 2015-10-28

### Added

* initial version with 2 drivers
