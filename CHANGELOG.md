# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

### Added

* The documentation site has a Slovak translation of every page under `/sk/`, with a language menu, `hreflang` links and Slovak structured data. English stays the default at the site root. `npm run docs:links` fails when a page exists in only one language ([#76](https://github.com/tomaj/hermes/issues/76)).
* The documentation site has a version menu. The root documents the latest version; older major versions are archived in a subfolder. 5.x, which had no site, is archived at `/5.x/` as its 5.0.0 README with a list of the known mistakes in its examples ([#76](https://github.com/tomaj/hermes/issues/76)).

### Fixed

* Docs: the HowTo structured data no longer contains the `<<<` snippet import lines, and two FAQ answers no longer say that RabbitMQ and SQS remove a message before the handler runs ([#76](https://github.com/tomaj/hermes/issues/76)).
* Docs: after FileSystem and Kafka landed, the home page, delayed and drivers overview FAQs, deployment note, and priority example name all six drivers instead of the older four; the contributing page documents `HERMES_TEST_KAFKA` and five integration env vars to match `composer test:integration`; the Slovak examples index includes the "are the examples tested?" FAQ ([#76](https://github.com/tomaj/hermes/issues/76)).

## 6.0.0 - 2026-10-19

Hermes 6.0. Upgrade steps for every breaking change are in [UPGRADE-6.0.md](UPGRADE-6.0.md).

### Added

* Integration tests run against Redis, RabbitMQ and moto (SQS) in CI. `HERMES_REQUIRE_SERVICES=1` turns a skipped integration test into a failure; `compose.yaml` and `composer test:integration` run them locally. The README shows Coveralls coverage ([#74](https://github.com/tomaj/hermes/issues/74))
* `Dispatcher` takes an optional fourth argument `?Random\Randomizer $randomizer` for the retry jitter. Pass one with a seeded engine for reproducible retry times. The default `new Randomizer()` replaces the global `rand()`, so `mt_srand()` no longer makes the retry times reproducible: pass a seeded `Randomizer` instead ([#82](https://github.com/tomaj/hermes/issues/82)).
* PHPStan also runs with bleeding edge, `phpstan/phpstan-deprecation-rules`, `phpstan/phpstan-phpunit`, `checkUninitializedProperties` and `reportUnmatchedIgnoredErrors`, without a baseline or ignored errors ([#85](https://github.com/tomaj/hermes/issues/85))
* Infection fails below 85% MSI and 90% covered code MSI, up from 65% for both. New tests cover the logger context arrays, every `MessageSerializer` field, the exact Redis commands of `PredisSetDriver` and `RedisSetDriver`, SQS queue attributes, and an SQS receive with no messages ([#86](https://github.com/tomaj/hermes/issues/86)).
* Rector configuration (`rector.php`) with the PHP 8.4 set and the dead code, code quality, type declaration and early return sets. `composer rector` reports changes and `composer check` runs it; `composer rector-fix` applies them. `src` and `tests` follow it ([#84](https://github.com/tomaj/hermes/issues/84)).
* PHPUnit fails the run on notices, deprecations, PHPUnit notices, deprecations and warnings, and an empty test suite, besides risky tests and warnings. A mock without expectations, which PHPUnit reports as a notice, fails the build; use `createStub()` instead. Deprecations that one dependency triggers in another dependency are ignored, so the run with the lowest dependencies passes ([#109](https://github.com/tomaj/hermes/issues/109)).
* With `ext-pcntl`, `Dispatcher::handle()` stops gracefully on `SIGTERM` and `SIGINT`: it finishes the current message, logs `Exiting hermes dispatcher - received SIGTERM` and returns. The signal stops only that `handle()` call. `ext-pcntl` is suggested in `composer.json` ([#116](https://github.com/tomaj/hermes/issues/116))
* CI runs the `e2e.sh` of every recipe in `examples/` and of the Laravel, Symfony and Nette integrations against the code of each pull request. `examples/ci/` holds the scripts; they install Hermes from the checkout through a Composer path repository ([#96](https://github.com/tomaj/hermes/issues/96)).
* `FileSystemDriver`: a queue in a local directory, for one server without a broker. One JSON file per message, written to `tmp/` and renamed into `new/`; workers claim a message with an atomic rename into `processing/` and delete it after the handler. FIFO order per queue, a folder per priority, delayed messages and retries through the due time in the file name, a visibility timeout that redelivers the message of a crashed worker, and `failed/` for messages that cannot be unserialized. Docs: [FileSystem driver](https://tomaj.github.io/hermes/guide/drivers/filesystem), example: `examples/filesystem` ([#17](https://github.com/tomaj/hermes/issues/17))
* `KafkaDriver` for Apache Kafka through `ext-rdkafka` 6 (a `suggest`): `send()` produces to one topic keyed by the message id and waits for the confirmation; `wait()` consumes in a consumer group and commits the offset after the handler, so delivery is at least once. No priorities and no delay, like RabbitMQ and SQS. Integration tests run against Kafka 4.3.1 (`compose.yaml`, `HERMES_TEST_KAFKA`). Docs: [Kafka driver](https://tomaj.github.io/hermes/guide/drivers/kafka), example: `examples/kafka` ([#14](https://github.com/tomaj/hermes/issues/14))

### Changed

* **BREAKING CHANGE**: `tracy/tracy` moved from `require` to `suggest`. `Dispatcher` still writes handler and driver exceptions to Tracy when it is installed and enabled, and works without it. Applications that use Tracy must require `tracy/tracy` themselves ([#72](https://github.com/tomaj/hermes/issues/72)).
* **BREAKING CHANGE**: `Dispatcher` retries a failed handler only when it implements the new `RetryableHandlerInterface` or uses `RetryTrait`, and calls `canRetry($message, $exception)`: `false` skips the retry. 5.x only checked that `canRetry()` and `maxRetry()` existed and never called `canRetry()`. Handlers with those methods but neither the interface nor the trait are no longer retried. `RetryTrait::canRetry()` takes the message and the exception ([#107](https://github.com/tomaj/hermes/issues/107)).
* **BREAKING CHANGE**: `Emitter::emit()` logs `Emitter send message #<id> to driver <class>` instead of `Dispatcher send message …`, so emitted messages no longer look as if the dispatcher sent them. Update log searches and alerts that match the old text. The deprecated `Dispatcher::emit()` keeps its text ([#111](https://github.com/tomaj/hermes/issues/111)).
* `Message` ids: `null` (the default) and `''` both generate a UUID 4; any other string, including `"0"`, is kept. The constructor signature is unchanged (`?string $messageId = null`). Docs and tests cover `null`, `''`, an explicit id and `"0"`, and examples use named arguments instead of a positional `null` ([#37](https://github.com/tomaj/hermes/issues/37)).
* Constructor property promotion, `readonly` for state that never changes, and native property types across `src/`. Docblocks that repeated native signatures and `{@inheritdoc}` blocks were removed; tags that carry information beyond the native type (`@return $this`, array value types, `@throws`) stay. Constructor parameter types, names, order and defaults are unchanged and only private members changed, so the public API is the same. Code that wrote those private properties through reflection or `Closure::bind()` after construction now gets an `Error`, because they are `readonly`. PHP `serialize()` and `var_export()` list `Message` properties in a different order; strings serialized by 5.x still unserialize ([#78](https://github.com/tomaj/hermes/issues/78), [#79](https://github.com/tomaj/hermes/issues/79)).
* **BREAKING CHANGE**: `LazyRabbitMqDriver` acknowledges messages manually: `basic_ack` after the callback, `basic_nack` with requeue when it throws, `basic_nack` without requeue when the body cannot be unserialized, prefetch 1. It consumed with `no_ack = true`, which lost the message when the worker crashed ([#70](https://github.com/tomaj/hermes/issues/70)).
* **BREAKING CHANGE**: `LazyRabbitMqDriver` declares a durable queue and publishes persistent messages (`delivery_mode` 2) by default. A queue created by 5.x is non-durable and RabbitMQ rejects the new declaration with `PRECONDITION_FAILED`: delete and recreate the queue, or pass the new `$durable = false` constructor argument ([#70](https://github.com/tomaj/hermes/issues/70)). RabbitMQ 4.3 and newer refuse non-durable queues unless `deprecated_features.permit.transient_nonexcl_queues = true` is set, so there neither 5.x nor `$durable = false` can declare its queue; UPGRADE-6.0.md has the details.
* `LazyRabbitMqDriver` counts handled messages, so `setMaxProcessItems()` works with it.
* `LazyRabbitMqDriver` accepts any `PhpAmqpLib\Connection\AbstractConnection`, not only `AMQPLazyConnection`, which php-amqplib deprecated. Pass a lazy connection, for example from `AMQPConnectionFactory` with `AMQPConnectionConfig::setIsLazy(true)`. Existing code that passes `AMQPLazyConnection` keeps working. The driver checks for active consumers with `AMQPChannel::is_consuming()` instead of the internal `$callbacks` property.
* `LazyRabbitMqDriver::wait()` blocks in `AMQPChannel::wait()` with a timeout instead of polling. An idle worker no longer spins one CPU core with the default `$refreshInterval` of `0`, and a non-zero interval no longer sleeps after every message. `$refreshInterval` is now the idle timeout in seconds (`0` means one second); on a timeout the driver checks shutdown and waits again, so an idle worker stops within one timeout.
* `ShutdownTrait` initializes its properties to `null`, so a driver that never gets a shutdown has no uninitialized properties.
* **Security**: `MessageSerializer` exceptions no longer contain the serialized message, which could carry personal data or tokens into logs and error trackers. They name the problem, the length and a SHA-256 prefix of the string, keep the `JsonException` as `previous` and use its code. Log searches for `Cannot unserialize message from` or `Invalid message format in` need the new texts ([#71](https://github.com/tomaj/hermes/issues/71)).
* **BREAKING CHANGE**: `PredisSetDriver` and `PredisShutdown` now support `predis/predis` `^2.3 || ^3.0`. Predis 1.x and 2.0 to 2.2 are no longer tested: they emit deprecations on PHP 8.4. Upgrade Predis together with Hermes.
* `PredisSetDriver::wait()` passes the schedule bound to `zrangebyscore` as a string with microseconds, as Predis 2 and 3 expect.
* Use PHP 8.4 idioms in the source: `::class` on objects instead of `get_class()`, `!== []` instead of `count() > 0`, `??=` for the default shutdown time, `array_map()` with a typed arrow function for SQS queue attributes, and an early return in the retry path. Rector's PHP 8.4 set reports no changes in `src/` ([#83](https://github.com/tomaj/hermes/issues/83)).
* `Message`, `Emitter`, `SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` are `readonly` classes. All their properties were already `readonly`; creating a dynamic property on them now throws an `Error` instead of a deprecation ([#83](https://github.com/tomaj/hermes/issues/83)).
* `LazyRabbitMqDriver::setupPriorityQueue()` and `AmazonSqsDriver::setupPriorityQueue()` declare the `never` return type, because they always throw `NotSupportedException`. The narrower type stays compatible with `DriverInterface`, and the classes are final, so no subclass can conflict. Static analysis now treats code after a call on these concrete types as unreachable: PHPStan reports it ([#83](https://github.com/tomaj/hermes/issues/83)).
* **BREAKING CHANGE**: Raised the minimum PHP version to 8.4. PHP 7.4 and 8.0 through 8.3 are no longer supported.
* `Dispatcher::DEFAULT_PRIORITY` is a typed class constant (`public const int`). `Dispatcher` is final, so nothing can redeclare it. Priorities stay plain integers; the reasons are in [UPGRADE-6.0.md](UPGRADE-6.0.md) ([#80](https://github.com/tomaj/hermes/issues/80)).
* `ramsey/uuid` now requires `^4.7`, so it installs on PHP 8.4 and 8.5. Tracy (suggested, see above) is tested with `^2.10.10`.
* `composer phpunit` and `composer test:integration` set `XDEBUG_MODE=off` and keep `--no-coverage`, so a local Xdebug in its default `develop` mode neither slows the run nor crashes it (`zend_mm_heap corrupted` on PHP 8.4). `composer coverage` and `composer infection` still turn coverage on.
* **BREAKING CHANGE**: `psr/log` now requires `^2 || ^3`; 1.x has no native types. `ext-json` is no longer listed, because PHP 8 always has it. `composer.json` fixes the `suggest` texts, drops the duplicate `cs` script, adds `composer test` and `composer check` (code style, PHPStan, tests), runs `composer phpunit` without coverage so it works without Xdebug or PCOV, sets `XDEBUG_MODE` for `composer coverage` and `composer infection` with `@putenv` so they also run on Windows, and sorts and normalizes the file ([#87](https://github.com/tomaj/hermes/issues/87)).
* **BREAKING CHANGE**: every concrete class in `src/` is `final` (`HermesException` stays open). Extend Hermes through its interfaces; [UPGRADE-6.0.md](UPGRADE-6.0.md) shows how. Methods that implement an interface carry `#[\Override]`, and PHPStan checks for it ([#81](https://github.com/tomaj/hermes/issues/81)).
* `DispatcherInterface::registerHandlers()` names its second parameter `$handlers`, like `Dispatcher` and the API reference. No code change is needed: named arguments resolve against the concrete class, which already used `$handlers`, and implementations may keep their own parameter name.
* **BREAKING CHANGE**: `AmazonSqsDriver` deletes a message only after it was handled, not right after `receiveMessage`. A worker crash or an exception from the callback leaves the message in SQS for the visibility timeout or a dead-letter queue. Delivery is at least once, so set `VisibilityTimeout` above your slowest handler ([#69](https://github.com/tomaj/hermes/issues/69)).
* **BREAKING CHANGE**: `ShutdownInterface::shouldShutdown()` and `shutdown()` take `DateTimeInterface` instead of `DateTime`. `Dispatcher` and `ShutdownTrait` keep the worker start time as a `DateTimeImmutable`, so a shutdown can no longer shift the dispatcher's own start time by modifying it. Callers can pass `DateTime` or `DateTimeImmutable`; custom implementations must change their parameter types ([#108](https://github.com/tomaj/hermes/issues/108)).
* CI measures coverage and runs Infection on PHP 8.5 instead of 8.4; the other PHPUnit jobs run with `--no-coverage`. A nightly PHP 8.6 job runs with `continue-on-error`, so its failure does not fail the workflow.

### Removed

* `.scrutinizer.yml`, `coverage.sh` and the root `Makefile`. Use `composer phpunit` and `composer coverage` instead. The Composer dist archive now contains only `src/`, the license, README, CHANGELOG, UPGRADE-6.0.md and `composer.json` ([#88](https://github.com/tomaj/hermes/issues/88)).
* The private `AmazonSqsDriver::$sleepInterval` and the empty-queue sleep that used it. Nothing could set it, so the branch never ran; long polling (`WaitTimeSeconds`) paces the loop as before ([#110](https://github.com/tomaj/hermes/issues/110)).

### Fixed

* **BREAKING CHANGE**: `Dispatcher` now catches any `Throwable` thrown by a handler, not only `Exception`. A PHP `Error` (for example `TypeError`) is logged, counts as a failed message and follows the normal retry rules instead of killing the worker ([#112](https://github.com/tomaj/hermes/issues/112))
* `SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` store the shutdown time with microseconds and stop only workers that started before it. A worker started in the same second as `hermes:restart` no longer exits at once. `SharedFileShutdown` ignores a future time, like the Redis shutdowns. See UPGRADE-6.0.md ([#115](https://github.com/tomaj/hermes/issues/115)).
* `RedisSetDriver` delivers delayed messages and retries again. Due entries are removed from the schedule sorted set with `ZREM`, and only the worker that removed an entry re-sends it. It used `SPOP`, which does not work on a sorted set, so they stayed in the schedule forever ([#95](https://github.com/tomaj/hermes/issues/95)).
* `PredisSetDriver` delivers a delayed message or retry once when several workers share the schedule. Like `RedisSetDriver`, only the worker whose `zrem` removed the due entry re-sends it. It ignored the `zrem` result, so concurrent workers could promote and deliver the same entry twice.
* `RedisSetDriver::wait()` and `PredisSetDriver::wait()` throw `UnknownPriorityException` when the priority filter lists a priority that was never registered, like `FileSystemDriver` and as their `@throws` and the API reference say. They skipped unknown priorities silently, so a worker started with a mistyped priority polled nothing for it.
* **BREAKING CHANGE**: `RedisSetDriver` and `PredisSetDriver` poll priority queues by priority number, highest first. They polled in reverse registration order, so the default priority 100 (registered in the constructor) always came last ([#50](https://github.com/tomaj/hermes/issues/50)). Workers that relied on the old order now process queues in a different order.
* **BREAKING CHANGE**: an `Exception` from the driver in `Dispatcher::handle()` is now logged at `error` (`Exiting hermes dispatcher - driver error: ...`) and rethrown, so the worker exits non-zero instead of returning silently with status 0 ([#113](https://github.com/tomaj/hermes/issues/113))
* README graceful-shutdown examples: add the missing `new`, use the phpredis `Redis` client with `RedisShutdown`, add `PredisShutdown` for `Predis\Client` (passing it to `RedisShutdown` is a `TypeError`), and fix the `ShutdownInterface` typo.
* README extending and handler examples match the 6.0 interfaces. The Gearman driver and JMS serializer sketches, which did not compile against `DriverInterface` and `SerializerInterface`, are replaced with a single-queue `InMemoryDriver` and a link to the extending guide. The handler examples declare `handle(): bool` and import `MessageInterface`, the logger example uses `$this->logger?->`, the retry note says which drivers delay a retry, scheduled execution names `executeAt` as the fifth `Message` constructor parameter, not the fourth, and the ZeroMQ driver note says that `tomaj/hermes-zmq-driver` requires Hermes `^2.1` (also in the FAQ and drivers overview).

## 5.0.0 - 2025-11-19

### Added

* Enhanced PHPStan configuration with strict rules and additional type checking
* Added `phpstan/phpstan-strict-rules` extension for improved code quality analysis
* Improved type safety with PHP 7.4+ typed properties in core classes
* Added comprehensive GitHub Actions workflows with PHP 7.4+ support
* Added security audit workflow with weekly vulnerability scanning
* Added composer validation and dependency caching in CI
* Added local code coverage reporting with HTML and Clover XML output
* Added GitHub Pages deployment for coverage reports
* Added `coverage.sh` helper script for generating coverage reports
* Added Makefile with coverage target

### Changed

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
* Coverage reports are now automatically published to GitHub Pages after successful test runs

### Fixed

* Fixed strict comparison issues in `Message` constructor
* Fixed boolean condition checks for better PHP 8+ compatibility
* Added proper return type hints to anonymous functions
* Improved JSON serialization error handling in `MessageSerializer`
* Removed implicitly nullable parameters for better type safety


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

[unreleased]: https://github.com/tomaj/hermes/compare/6.0.0...HEAD
