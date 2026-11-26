# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased][unreleased]

### Changed

* `RedisSetDriver::send()` and `PredisSetDriver::send()` throw `UnknownPriorityException` for a priority that was not registered with `setupPriorityQueue()` also when the message is delayed, as they already did for a message sent right away. 6.0 scheduled such a message and later delivered it at the default priority. Register the priority on the emitter too ([#183](https://github.com/tomaj/hermes/issues/183)).

### Fixed

* A retry keeps the priority of the failed message. `Dispatcher` passes the priority the driver handed to the callback on to `send()`, so with `FileSystemDriver` and the Redis drivers a failing priority-200 message is retried at 200, and a worker started with `handle([200])` sees the retry. 6.0 sent every retry at the default priority. Drivers without priorities (RabbitMQ, SQS, Kafka, or a custom driver that calls the callback with the message only) get the default priority as before ([#183](https://github.com/tomaj/hermes/issues/183)).
* `RedisSetDriver` and `PredisSetDriver` deliver a delayed message or retry at its own priority. A message with the default priority is scheduled in `$scheduleKey` (`hermes_schedule`) as before, any other priority in its own sorted set `$scheduleKey:<priority>` (for example `hermes_schedule:200`). `wait()` promotes the due entries of every registered priority with that priority, one `ZRANGEBYSCORE` per priority and loop. Entries that 6.0 wrote stay in `hermes_schedule` and are promoted at the default priority. 6.0 workers read only `hermes_schedule`: before you roll back to 6.0, wait until the `hermes_schedule:<priority>` sets are empty ([#183](https://github.com/tomaj/hermes/issues/183)).

## [6.0.0] - 2026-10-19

Hermes 6.0. Upgrade steps for every breaking change are in [UPGRADE-6.0.md](UPGRADE-6.0.md).

### Added

* Integration tests run against Redis, RabbitMQ and moto (SQS) in CI. `HERMES_REQUIRE_SERVICES=1` turns a skipped integration test into a failure; `compose.yaml` and `composer test:integration` run them locally. The README shows Coveralls coverage ([#74](https://github.com/tomaj/hermes/issues/74))
* `Dispatcher` takes an optional fourth argument `?Random\Randomizer $randomizer` for the retry jitter. Pass one with a seeded engine for reproducible retry times. The default `new Randomizer()` replaces the global `rand()`, so `mt_srand()` no longer makes the retry times reproducible: pass a seeded `Randomizer` instead ([#82](https://github.com/tomaj/hermes/issues/82)).
* PHPStan also runs with bleeding edge, `phpstan/phpstan-deprecation-rules`, `phpstan/phpstan-phpunit`, `checkUninitializedProperties` and `reportUnmatchedIgnoredErrors`, without a baseline or ignored errors ([#85](https://github.com/tomaj/hermes/issues/85))
* Infection fails below 85% MSI and 90% covered code MSI, up from 65% for both. New tests cover the logger context arrays, every `MessageSerializer` field, the exact Redis commands of `PredisSetDriver` and `RedisSetDriver`, SQS queue attributes, and an SQS receive with no messages ([#86](https://github.com/tomaj/hermes/issues/86)).
* The Infection mutant timeout is 30 seconds, up from 10, so tests that wait on purpose (RabbitMQ, shutdown, idle polls) are not cut off; only mutants that never stop still time out. New tests kill the mutants that escaped: the RabbitMQ consumer flags and close calls, the Redis and Predis idle poll and priority pick, and the `SharedFileShutdown` stat cache. `infection.json5` ignores the equivalent mutants left, each with a comment: `>` to `>=` on the `executeAt` check in the Redis and Predis `send()`, removing `clearstatcache()` in `SharedFileShutdown`, and the `LazyRabbitMqDriver` `$refreshInterval` default. The tests write Tracy logs and `FileSystemDriver` files into the system temp directory, not the repository ([#167](https://github.com/tomaj/hermes/pull/167)).
* Rector configuration (`rector.php`) with the PHP 8.4 set and the dead code, code quality, type declaration and early return sets. `composer rector` reports changes and `composer check` runs it; `composer rector-fix` applies them. `src` and `tests` follow it ([#84](https://github.com/tomaj/hermes/issues/84)).
* PHPUnit fails the run on notices, deprecations, PHPUnit notices, deprecations and warnings, and an empty test suite, besides risky tests and warnings. A mock without expectations, which PHPUnit reports as a notice, fails the build; use `createStub()` instead. Deprecations that one dependency triggers in another dependency are ignored, so the run with the lowest dependencies passes ([#109](https://github.com/tomaj/hermes/issues/109)).
* With `ext-pcntl`, `Dispatcher::handle()` stops gracefully on `SIGTERM` and `SIGINT`: it finishes the current message, logs `Exiting hermes dispatcher - received SIGTERM` and returns. The signal stops only that `handle()` call. `ext-pcntl` is suggested in `composer.json` ([#116](https://github.com/tomaj/hermes/issues/116))
* CI runs the `e2e.sh` of every recipe in `examples/` and of the Laravel, Symfony and Nette integrations against the code of each pull request. `examples/ci/` holds the scripts; they install Hermes from the checkout through a Composer path repository ([#96](https://github.com/tomaj/hermes/issues/96)).
* `FileSystemDriver`: a queue in a local directory, for one server without a broker. One JSON file per message, written to `tmp/` and renamed into `new/`; workers claim a message with an atomic rename into `processing/` and delete it after the handler. FIFO order per queue, a folder per priority, delayed messages and retries through the due time in the file name, a visibility timeout that redelivers the message of a crashed worker, and `failed/` for messages that cannot be unserialized. Docs: [FileSystem driver](https://tomaj.github.io/hermes/guide/drivers/filesystem), example: `examples/filesystem` ([#17](https://github.com/tomaj/hermes/issues/17))
* `KafkaDriver` for Apache Kafka through `ext-rdkafka` 6 (a `suggest`): `send()` produces to one topic keyed by the message id and waits for the confirmation; `wait()` consumes in a consumer group and commits the offset after the handler, so delivery is at least once. No priorities and no delay, like RabbitMQ and SQS. Integration tests run against Kafka 4.3.1 (`compose.yaml`, `HERMES_TEST_KAFKA`). Docs: [Kafka driver](https://tomaj.github.io/hermes/guide/drivers/kafka), example: `examples/kafka` ([#14](https://github.com/tomaj/hermes/issues/14))
* `BufferedEmitter` wraps an `EmitterInterface` and holds messages until `flush()`, so a message about a row is sent only after the database transaction commits; `discard()` drops them on rollback. An optional predicate decides per `emit()` whether to buffer, for example `[$db, 'isInTransaction']`. `flush()` keeps the original order and priorities and, when the inner emitter throws, keeps the unsent messages for the next `flush()`. It is not a transactional outbox: messages buffered in a process that dies are lost. Docs: [Emitting after a database commit](https://tomaj.github.io/hermes/guide/buffered-emitter), example: `examples/emit-after-commit` ([#171](https://github.com/tomaj/hermes/pull/171))
* The documentation site has a Slovak translation of every page under `/sk/`, with a language menu, `hreflang` links and Slovak structured data. English stays the default at the site root. `npm run docs:links` fails when a page exists in only one language ([#76](https://github.com/tomaj/hermes/issues/76)).
* The documentation site has a version menu. The root documents the latest version; older major versions are archived in a subfolder. 5.x, which had no site, is archived at `/5.x/` as its 5.0.0 README with a list of the known mistakes in its examples ([#76](https://github.com/tomaj/hermes/issues/76)).

### Changed

* **BREAKING CHANGE**: `tracy/tracy` moved from `require` to `suggest`. `Dispatcher` still writes handler and driver exceptions to Tracy when it is installed and enabled, and works without it. Applications that use Tracy must require `tracy/tracy` themselves ([#72](https://github.com/tomaj/hermes/issues/72)).
* **BREAKING CHANGE**: `Dispatcher` calls `canRetry($message, $exception)` of a handler that implements the new `RetryableHandlerInterface`: `false` skips the retry. 5.x only checked that `canRetry()` and `maxRetry()` existed and never called `canRetry()`. `RetryTrait::canRetry()` takes the message and the exception. `EchoHandler` and the handlers in `examples/` implement the interface ([#107](https://github.com/tomaj/hermes/issues/107)).
* **BREAKING CHANGE**: `Emitter::emit()` logs `Emitter send message #<id> to driver <class>` instead of `Dispatcher send message …`, so emitted messages no longer look as if the dispatcher sent them. Update log searches and alerts that match the old text. The deprecated `Dispatcher::emit()` keeps its text ([#111](https://github.com/tomaj/hermes/issues/111)).
* `Emitter::emit()` log context also carries `retries` and `execute_at`, the same keys as the `Dispatcher` lines. The logging guide (EN and SK) warns that every `info` line carries the full `payload` and shows how to keep it out of the logs ([#167](https://github.com/tomaj/hermes/pull/167)).
* `Message` ids: `null` (the default) and `''` both generate a UUID 4; any other string, including `"0"`, is kept. The constructor signature is unchanged (`?string $messageId = null`). Docs and tests cover `null`, `''`, an explicit id and `"0"`, and examples use named arguments instead of a positional `null` ([#37](https://github.com/tomaj/hermes/issues/37)).
* Constructor property promotion, `readonly` for state that never changes, and native property types across `src/`. Docblocks that repeated native signatures and `{@inheritdoc}` blocks were removed; tags that carry information beyond the native type (`@return $this`, array value types, `@throws`) stay. Apart from the new optional arguments (`Dispatcher::$randomizer`, `LazyRabbitMqDriver::$durable`) and the wider `LazyRabbitMqDriver` connection type, constructor parameter types, names, order and defaults are unchanged. Code that wrote those private properties through reflection or `Closure::bind()` after construction now gets an `Error`, because they are `readonly`. PHP `serialize()` and `var_export()` list `Message` properties in a different order; strings serialized by 5.x still unserialize ([#78](https://github.com/tomaj/hermes/issues/78), [#79](https://github.com/tomaj/hermes/issues/79)).
* **BREAKING CHANGE**: `LazyRabbitMqDriver` acknowledges messages manually: `basic_ack` after the callback, `basic_nack` with requeue when it throws, `basic_nack` without requeue when the body cannot be unserialized, prefetch 1. It consumed with `no_ack = true`, which lost the message when the worker crashed ([#70](https://github.com/tomaj/hermes/issues/70)).
* **BREAKING CHANGE**: `LazyRabbitMqDriver` declares a durable queue and publishes persistent messages (`delivery_mode` 2) by default. A queue created by 5.x is non-durable and RabbitMQ rejects the new declaration with `PRECONDITION_FAILED`: delete and recreate the queue, or pass the new `$durable = false` constructor argument ([#70](https://github.com/tomaj/hermes/issues/70)). RabbitMQ 4.3 and newer refuse non-durable queues unless `deprecated_features.permit.transient_nonexcl_queues = true` is set, so there neither 5.x nor `$durable = false` can declare its queue; UPGRADE-6.0.md has the details.
* `LazyRabbitMqDriver` counts handled messages, so `setMaxProcessItems()` works with it ([#100](https://github.com/tomaj/hermes/pull/100)).
* `LazyRabbitMqDriver` accepts any `PhpAmqpLib\Connection\AbstractConnection`, not only `AMQPLazyConnection`, which php-amqplib deprecated. Pass a lazy connection, for example from `AMQPConnectionFactory` with `AMQPConnectionConfig::setIsLazy(true)`. Existing code that passes `AMQPLazyConnection` keeps working. The driver checks for active consumers with `AMQPChannel::is_consuming()` instead of the internal `$callbacks` property ([#126](https://github.com/tomaj/hermes/pull/126)).
* `LazyRabbitMqDriver::wait()` blocks in `AMQPChannel::wait()` with a timeout instead of polling. An idle worker no longer spins one CPU core with the default `$refreshInterval` of `0`, and a non-zero interval no longer sleeps after every message. `$refreshInterval` is now the idle timeout in seconds (`0` means one second); on a timeout the driver checks shutdown and waits again, so an idle worker stops within one timeout ([#165](https://github.com/tomaj/hermes/pull/165)).
* `ShutdownTrait` initializes its properties to `null`, so a driver that never gets a shutdown has no uninitialized properties ([#126](https://github.com/tomaj/hermes/pull/126)).
* **Security**: `MessageSerializer` exceptions no longer contain the serialized message, which could carry personal data or tokens into logs and error trackers. They name the problem, the length and a SHA-256 prefix of the string, keep the `JsonException` as `previous` and use its code. Log searches for `Cannot unserialize message from` or `Invalid message format in` need the new texts ([#71](https://github.com/tomaj/hermes/issues/71)).
* **BREAKING CHANGE**: `PredisSetDriver` and `PredisShutdown` now support `predis/predis` `^2.3 || ^3.0`. Predis 1.x and 2.0 to 2.2 are no longer tested: they emit deprecations on PHP 8.4. Upgrade Predis together with Hermes ([#98](https://github.com/tomaj/hermes/pull/98)).
* `composer.json` declares a `conflict` with `predis/predis` `<2.3`, so Composer no longer installs Hermes 6 next to an untested Predis ([#167](https://github.com/tomaj/hermes/pull/167)).
* `PredisSetDriver::wait()` passes the schedule bound to `zrangebyscore` as a string with microseconds, as Predis 2 and 3 expect. `RedisSetDriver` formats the bound the same way ([#98](https://github.com/tomaj/hermes/pull/98)).
* `RedisSetDriver` and `PredisSetDriver` throw `InvalidArgumentException` for a negative `$refreshInterval` in the constructor, like `FileSystemDriver`. 5.x accepted it and failed only on the first idle pass of `wait()`, where `sleep()` throws `ValueError` ([#169](https://github.com/tomaj/hermes/pull/169)).
* Use PHP 8.4 idioms in the source: `::class` on objects instead of `get_class()`, `!== []` instead of `count() > 0`, `??=` for the default shutdown time, `array_map()` with a typed arrow function for SQS queue attributes, and an early return in the retry path. Rector's PHP 8.4 set reports no changes in `src/` ([#83](https://github.com/tomaj/hermes/issues/83)).
* The `AmazonSqsDriver` constructor throws `Tomaj\Hermes\Driver\AmazonSqsDriverException`, a `HermesException`, when the `createQueue` result has no `QueueUrl`, so catching `HermesException` covers every runtime failure of the package. 5.x kept a `null` queue URL and sent it to SQS on the first `send()` or `wait()` ([#193](https://github.com/tomaj/hermes/issues/193)).
* `Message`, `Emitter`, `SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` are `readonly` classes. All their properties were already `readonly`; creating a dynamic property on them now throws an `Error` instead of a deprecation ([#83](https://github.com/tomaj/hermes/issues/83)).
* `composer.json` `homepage` points to the documentation site, `support` lists the docs and the security policy, and every Composer script has a description (`composer list`). The repository has a `CODEOWNERS` file, the README shows license and PHP version badges, and SECURITY.md covers Kafka and `FileSystemDriver` ([#191](https://github.com/tomaj/hermes/issues/191)).
* `LazyRabbitMqDriver::setupPriorityQueue()` and `AmazonSqsDriver::setupPriorityQueue()` declare the `never` return type, because they always throw `NotSupportedException`. The narrower type stays compatible with `DriverInterface`, and the classes are final, so no subclass can conflict. Static analysis now treats code after a call on these concrete types as unreachable: PHPStan reports it ([#83](https://github.com/tomaj/hermes/issues/83)).
* **BREAKING CHANGE**: Raised the minimum PHP version to 8.4. PHP 7.4 and 8.0 through 8.3 are no longer supported ([#67](https://github.com/tomaj/hermes/pull/67)).
* `Dispatcher::DEFAULT_PRIORITY` is a typed class constant (`public const int`). `Dispatcher` is final, so nothing can redeclare it. Priorities stay plain integers; the reasons are in [UPGRADE-6.0.md](UPGRADE-6.0.md) ([#80](https://github.com/tomaj/hermes/issues/80)).
* `ramsey/uuid` now requires `^4.7`, so it installs on PHP 8.4 and 8.5. Tracy (suggested, see above) is tested with `^2.10.10` ([#67](https://github.com/tomaj/hermes/pull/67)).
* `composer phpunit` and `composer test:integration` set `XDEBUG_MODE=off` and keep `--no-coverage`, so a local Xdebug in its default `develop` mode neither slows the run nor crashes it (`zend_mm_heap corrupted` on PHP 8.4). `composer coverage` and `composer infection` still turn coverage on ([#162](https://github.com/tomaj/hermes/pull/162)).
* **BREAKING CHANGE**: `psr/log` now requires `^2 || ^3`; 1.x has no native types. `ext-json` is no longer listed, because PHP 8 always has it. `composer.json` fixes the `suggest` texts, drops the duplicate `cs` script, adds `composer test` and `composer check` (code style, PHPStan, tests), runs `composer phpunit` without coverage so it works without Xdebug or PCOV, sets `XDEBUG_MODE` for `composer coverage` and `composer infection` with `@putenv` so they also run on Windows, and sorts and normalizes the file ([#87](https://github.com/tomaj/hermes/issues/87)).
* **BREAKING CHANGE**: every concrete class in `src/` is `final` (`HermesException` stays open). Extend Hermes through its interfaces; [UPGRADE-6.0.md](UPGRADE-6.0.md) shows how. Methods that implement an interface carry `#[\Override]`, and PHPStan checks for it ([#81](https://github.com/tomaj/hermes/issues/81)).
* `DispatcherInterface::registerHandlers()` names its second parameter `$handlers`, like `Dispatcher` and the API reference. No code change is needed: named arguments resolve against the concrete class, which already used `$handlers`, and implementations may keep their own parameter name ([#161](https://github.com/tomaj/hermes/pull/161)).
* **BREAKING CHANGE**: `AmazonSqsDriver` deletes a message only after it was handled, not right after `receiveMessage`. A worker crash or an exception from the callback leaves the message in SQS for the visibility timeout or a dead-letter queue. Delivery is at least once, so set `VisibilityTimeout` above your slowest handler ([#69](https://github.com/tomaj/hermes/issues/69)).
* **BREAKING CHANGE**: `ShutdownInterface::shouldShutdown()` and `shutdown()` take `DateTimeInterface` instead of `DateTime`. `Dispatcher` and `ShutdownTrait` keep the worker start time as a `DateTimeImmutable`, so a shutdown can no longer shift the dispatcher's own start time by modifying it. Callers can pass `DateTime` or `DateTimeImmutable`; custom implementations must change their parameter types ([#108](https://github.com/tomaj/hermes/issues/108)).
* CI runs the tests on PHP 8.4 (also with the lowest dependencies), 8.5 and 8.6. Coverage, the 95% coverage check, Coveralls and Infection run on PHP 8.6; the other PHPUnit jobs run with `--no-coverage`. A nightly PHP 8.7 job runs with `continue-on-error`, so its failure does not fail the workflow. `shivammathur/setup-php` is pinned to a `main` commit after 2.40.0, which installs PHP 8.6 stable instead of a nightly build ([#158](https://github.com/tomaj/hermes/pull/158)).

### Deprecated

* A handler that has `canRetry()` and `maxRetry()` (its own methods or `RetryTrait`) but does not implement `RetryableHandlerInterface` is still retried exactly as in 5.x: while `getRetries() < maxRetry()`, without calling `canRetry()`. `Dispatcher` triggers `E_USER_DEPRECATED` and logs a warning naming the class, once per class and process. Implement `RetryableHandlerInterface`; the fallback is removed in 7.0 ([#170](https://github.com/tomaj/hermes/pull/170)).
* `Dispatcher::emit()`, deprecated since 2.0, is removed in 7.0. Its first call on each `Dispatcher` now triggers `E_USER_DEPRECATED` (silenced with `@`) and logs a `warning`; the message is still sent and logged as before. Use `Emitter::emit()` ([#177](https://github.com/tomaj/hermes/pull/177)).

### Removed

* `.scrutinizer.yml`, `coverage.sh` and the root `Makefile`. Use `composer phpunit` and `composer coverage` instead. The Composer dist archive now contains only `src/`, the license, README, CHANGELOG, UPGRADE-6.0.md and `composer.json` ([#88](https://github.com/tomaj/hermes/issues/88)).
* The private `AmazonSqsDriver::$sleepInterval` and the empty-queue sleep that used it. Nothing could set it, so the branch never ran; long polling (`WaitTimeSeconds`) paces the loop as before ([#110](https://github.com/tomaj/hermes/issues/110)).

### Fixed

* **BREAKING CHANGE**: for a driver that uses `ShutdownTrait`, the `Dispatcher` callback returns the handler result also when a shutdown or a signal was requested during the message, and the driver stops at its own `checkShutdown()`. 5.x threw `ShutdownException` from the callback after the message, so a driver that records the result (for example a failed-task table) lost it on every stop. Any other driver, also one with its own `setShutdown()`, still gets the exception after the message ([#179](https://github.com/tomaj/hermes/pull/179)).
* A failed `send()` of a retry copy is logged at `error` as `Retry of message #<id> (<type>) lost - driver send failed: …` before it ends `handle()` as a driver error. Before, the log showed only the handler error and the driver error, not that the retry was lost ([#179](https://github.com/tomaj/hermes/pull/179)).
* `Dispatcher::handle()` casts numeric-string priorities such as `'110'` from argv or a console option to `int`, and throws `InvalidArgumentException` for any other string (`'high'`, `''`, `'1.9'`). The drivers type their priority checks as `int`, so a string priority ended the worker with a `TypeError` ([#179](https://github.com/tomaj/hermes/pull/179)).
* Docs: the HowTo structured data no longer contains the `<<<` snippet import lines, and two FAQ answers no longer say that RabbitMQ and SQS remove a message before the handler runs ([#76](https://github.com/tomaj/hermes/issues/76)).
* Docs: after FileSystem and Kafka landed, the home page, delayed and drivers overview FAQs, deployment note, and priority example name all six drivers instead of the older four; the contributing page documents `HERMES_TEST_KAFKA` and five integration env vars to match `composer test:integration`; the Slovak examples index includes the "are the examples tested?" FAQ ([#76](https://github.com/tomaj/hermes/issues/76)).
* **BREAKING CHANGE**: `Dispatcher` now catches any `Throwable` thrown by a handler, not only `Exception`. A PHP `Error` (for example `TypeError`) is logged, counts as a failed message and follows the normal retry rules instead of killing the worker ([#112](https://github.com/tomaj/hermes/issues/112))
* `SharedFileShutdown`, `RedisShutdown` and `PredisShutdown` store the shutdown time with microseconds and stop only workers that started before it. A worker started in the same second as `hermes:restart` no longer exits at once. `SharedFileShutdown` ignores a future time, like the Redis shutdowns. See UPGRADE-6.0.md ([#115](https://github.com/tomaj/hermes/issues/115)).
* `RedisSetDriver` delivers delayed messages and retries again. Due entries are removed from the schedule sorted set with `ZREM`, and only the worker that removed an entry re-sends it. It used `SPOP`, which does not work on a sorted set, so they stayed in the schedule forever ([#95](https://github.com/tomaj/hermes/issues/95)).
* `PredisSetDriver` delivers a delayed message or retry once when several workers share the schedule. Like `RedisSetDriver`, only the worker whose `zrem` removed the due entry re-sends it. It ignored the `zrem` result, so concurrent workers could promote and deliver the same entry twice ([#163](https://github.com/tomaj/hermes/pull/163)).
* `RedisSetDriver::wait()` and `PredisSetDriver::wait()` throw `UnknownPriorityException` when the priority filter lists a priority that was never registered, like `FileSystemDriver` and as their `@throws` and the API reference say. They skipped unknown priorities silently, so a worker started with a mistyped priority polled nothing for it ([#159](https://github.com/tomaj/hermes/pull/159)).
* **BREAKING CHANGE**: `RedisSetDriver` and `PredisSetDriver` poll priority queues by priority number, highest first. They polled in reverse registration order, so the default priority 100 (registered in the constructor) always came last ([#50](https://github.com/tomaj/hermes/issues/50)). Workers that relied on the old order now process queues in a different order.
* **BREAKING CHANGE**: an `Exception` from the driver in `Dispatcher::handle()` is now logged at `error` (`Exiting hermes dispatcher - driver error: ...`) and rethrown, so the worker exits non-zero instead of returning silently with status 0 ([#113](https://github.com/tomaj/hermes/issues/113))
* README graceful-shutdown examples: add the missing `new`, use the phpredis `Redis` client with `RedisShutdown`, add `PredisShutdown` for `Predis\Client` (passing it to `RedisShutdown` is a `TypeError`), and fix the `ShutdownInterface` typo ([#160](https://github.com/tomaj/hermes/pull/160)).
* `MessageSerializerTest` passes on PHP 8.6, which appends the position to JSON error messages (`Syntax error near location 1:12`). The tests take the expected text from the running PHP, and UPGRADE-6.0.md says that this part of the `invalid JSON (...)` message depends on the PHP version. The library never depended on the text; it passes the `JSON_ERROR_*` code as the exception code ([#176](https://github.com/tomaj/hermes/pull/176)).
* README extending and handler examples match the 6.0 interfaces. The Gearman driver and JMS serializer sketches, which did not compile against `DriverInterface` and `SerializerInterface`, are replaced with a single-queue `InMemoryDriver` and a link to the extending guide. The handler examples declare `handle(): bool` and import `MessageInterface`, the logger example uses `$this->logger?->`, the retry note says which drivers delay a retry, scheduled execution names `executeAt` as the fifth `Message` constructor parameter, not the fourth, and the ZeroMQ driver note says that `tomaj/hermes-zmq-driver` requires Hermes `^2.1` (also in the FAQ and drivers overview) ([#164](https://github.com/tomaj/hermes/pull/164)).

## [5.0.0] - 2025-11-19

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


## [4.2.0] - 2024-04-29

### Added

* **BREAKING CHANGE**: changed interface for Dispatcher - added ability to unregister handlers


## [4.1.0] - 2023-12-17

### Changed

- RedisSetDriver - add atomicity to scheduled set


## [4.0.1] - 2021-11-23

### Changed

* Fixed Predis driver when retry is being scheduled [#48](https://github.com/tomaj/hermes/issues/48)


## [4.0.0] - 2021-02-02

### Changed

* **BREAKING CHANGE**: Renamed all **Restart** to **Shutdown**
* **BREAKING CHANGE**: Removed deprecated *RabbitMqDriver**. You can use LazyRabbitMqDriver
* **BREAKING CHANGE**: Splitted **RedisSetDriver** to two implementations based on how it is interacting with redis. For using Redis php extension you can use old **RedisSetDriver**, for using predis package you have to use **PredisSetDriver**
* Added clearstatcache() into SharedFileShutdown



## [3.1.0] - 2020-10-22

### Changed

* Added support for *soft restart* to all drivers
* Added `consumer tag` to LazyRabbitMq driver for consumer
* Added support for _max items_ and _restart_ for LazyRabbitMq Driver
* updated restart policy for AmazonSQS Driver  


## [3.0.1] - 2020-10-16

### Changed

* Fixed `RedisRestart::restart()` response for Predis instance. `\Predis\Client::set()` returns object _(with 'OK' payload)_ instead of bool.
* Deprecated `RabbitMqDriver` (will be removed in 4.0.0) - use `LazyRabbitMqDriver` instead
* Fixed error while parsing message with invalid UTF8 character

## [3.0.0] - 2020-10-13

### Added

* `RedisRestart` - implementation of `RestartInterface` allowing graceful shutdown of Hermes through Redis entry.
* **BREAKING CHANGE**: Added `RestartInterface::restart()` method to initiate Hermes restart without knowing the requirements of used `RestartInterface` implementation. _Updated all related tests._
* **BREAKING CHANGE**: Removed support for ZeroMQ - driver moved into [separated package](https://github.com/tomaj/hermes-zmq-driver)
* Upgraded phpunit and tests
* **BREAKING CHANGE** Drop support for php 7.1

## [2.2.3] - 2021-01-31

Maintenance release of the 2.x line, tagged after 3.1.0.

### Fixed

* `SharedFileRestart` clears PHP's stat cache before it checks the restart file, so a long-running worker notices a restart requested after it started instead of reading a cached file time.

## [2.2.2] - 2019-10-29

### Fixed

* `LazyRabbitMqDriver` no longer calls `basic_ack` after the callback. Since 2.2.1 it consumes with `no_ack = true`, so RabbitMQ already removed the message and the extra acknowledgement of an unknown delivery tag closed the channel ([#30](https://github.com/tomaj/hermes/pull/30)).

## [2.2.1] - 2019-09-11

`LazyRabbitMqDriver` changes back to the queue and consumer settings used before 2.2.0.

### Changed

* `LazyRabbitMqDriver` declares the queue as non-durable again, so it works with queues that were created by Hermes before 2.2.0 (2.2.0 declared a durable queue, which RabbitMQ rejects for an existing non-durable queue).
* `LazyRabbitMqDriver` consumes with `no_ack = true` (automatic acknowledgement). A message is removed from the queue when it is delivered, so it is lost if the worker crashes while handling it.

## [2.2.0] - 2019-07-12

### Added

* Fixed loss of messages when the handler crashes and mechanism of retries for RabbitMQ Drivers 

## [2.1.1] - 2019-07-08

Fixes for delayed execution and retries introduced in 2.1.0.

### Added

* `Dispatcher::registerHandlers()` (also in `DispatcherInterface`) registers several handlers for one message type at once.
* The dispatcher log context carries the message `retries` and `execute_at`.

### Fixed

* `MessageSerializer` converts `execute_at` to a float when unserializing, so a scheduled or retried message restored by a driver keeps a valid execution time.

## [2.1.0] - 2019-07-06

### Added

* Added retry to handlers

## [2.0.1] - 2019-03-08

### Added

* Added missing `handle()` method to `DispatcherInterface` ([#25](https://github.com/tomaj/hermes/pull/25)).

### Fixed

* Syntax error in the new `DispatcherInterface::handle()` declaration ([#26](https://github.com/tomaj/hermes/pull/26)).
* `Closure` type in the `DriverInterface::wait()` docblock ([#27](https://github.com/tomaj/hermes/pull/27)).

## [2.0.0] - 2018-10-17

### Added

* Message now support scheduled parameter - Driver needs to support this behaviour.
* Type hints

### Changed

* **BREAKING CHANGE**: Dropped support for old PHP versions: Hermes requires PHP 7.1 or newer.

## [1.3.1] - 2016-11-27

Dependency fix: Hermes no longer pins `psr/log` to one exact version.

### Fixed

* `psr/log` is required as `^1.0` instead of exactly `1.0.0`, so Hermes installs next to applications and loggers (for example Monolog) that need a newer `psr/log` 1.x.

## [1.3.0] - 2016-10-27

### Changed

* Deprecated `emit()` in `Dispatcher` - moved to the new `Emitter` class (`EmitterInterface`).
* **BREAKING CHANGE**: `emit()` was removed from `DispatcherInterface`; implementations and type hints that call `emit()` through the interface use `EmitterInterface` instead.
* **BREAKING CHANGE**: Dropped support for PHP 5.4: Hermes requires PHP 5.5 or newer.

## [1.2.0] - 2016-09-26

### Changed

* Amazon aws library updated to version 3 in composer - still works with v2 but you have to initialize Sqs client in v2 style

## [1.1.0] - 2016-09-05

### Added

* Amazon SQS driver

## [1.0.0] - 2016-09-02

### Added

* First stable version
* Added ACK to rabbitmq driver

## [0.4.0] - 2016-04-26

### Added

* Added RabbitMQ Lazy driver

## [0.3.0] - 2016-03-23

### Added

* Added possibility to gracefull restart worker with RestartInterface
* Added Tracy debugger log when error occured

## [0.2.0] - 2015-10-30

### Changed

* Handling responses from handlers.
* Tests structure refactored

## [0.1.0] - 2015-10-28

### Added

* initial version with 2 drivers

[unreleased]: https://github.com/tomaj/hermes/compare/6.0.0...HEAD
[6.0.0]: https://github.com/tomaj/hermes/compare/5.0.0...6.0.0
[5.0.0]: https://github.com/tomaj/hermes/compare/4.2.0...5.0.0
[4.2.0]: https://github.com/tomaj/hermes/compare/4.1.0...4.2.0
[4.1.0]: https://github.com/tomaj/hermes/compare/4.0.1...4.1.0
[4.0.1]: https://github.com/tomaj/hermes/compare/4.0.0...4.0.1
[4.0.0]: https://github.com/tomaj/hermes/compare/3.1.0...4.0.0
[3.1.0]: https://github.com/tomaj/hermes/compare/3.0.1...3.1.0
[3.0.1]: https://github.com/tomaj/hermes/compare/3.0.0...3.0.1
[3.0.0]: https://github.com/tomaj/hermes/compare/2.2.2...3.0.0
[2.2.3]: https://github.com/tomaj/hermes/compare/2.2.2...2.2.3
[2.2.2]: https://github.com/tomaj/hermes/compare/2.2.1...2.2.2
[2.2.1]: https://github.com/tomaj/hermes/compare/2.2.0...2.2.1
[2.2.0]: https://github.com/tomaj/hermes/compare/2.1.1...2.2.0
[2.1.1]: https://github.com/tomaj/hermes/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/tomaj/hermes/compare/2.0.1...2.1.0
[2.0.1]: https://github.com/tomaj/hermes/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/tomaj/hermes/compare/1.3.1...2.0.0
[1.3.1]: https://github.com/tomaj/hermes/compare/1.3.0...1.3.1
[1.3.0]: https://github.com/tomaj/hermes/compare/1.2.0...1.3.0
[1.2.0]: https://github.com/tomaj/hermes/compare/1.1.0...1.2.0
[1.1.0]: https://github.com/tomaj/hermes/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/tomaj/hermes/compare/0.4.0...1.0.0
[0.4.0]: https://github.com/tomaj/hermes/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/tomaj/hermes/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/tomaj/hermes/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/tomaj/hermes/releases/tag/0.1.0
