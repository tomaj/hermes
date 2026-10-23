# Change Log
All notable changes to this project will be documented in this file.
Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## [Unreleased]

## 5.1.0 - unreleased

5.1.0 is a bridge to 6.0. It changes no behaviour unless you opt in; it reports, as `E_USER_DEPRECATED` notices, the code that 6.0 will treat differently. The notices are silenced with `@` (as Symfony does), so they show only in error handlers that collect deprecations (PHPUnit, Symfony's ErrorHandler, a custom `set_error_handler`).

### Added

* `Tomaj\Hermes\Handler\RetryableHandlerInterface` with the 6.0 signature. A handler that implements it gets the 6.0 retry rules now: `canRetry($message, $exception)` is called and the retry is skipped when it returns false
* `RetryTrait::canRetry()` accepts the optional `$message` and `$exception` arguments that 6.0 passes
* `LazyRabbitMqDriver` takes a sixth argument `bool $durable = false`. `true` declares a durable queue and publishes persistent messages (unless `delivery_mode` is set in the message properties), as 6.0 does by default

### Deprecated

* Extending `Dispatcher`, `Emitter`, `Message`, the drivers and the shutdowns: these classes are `final` in 6.0 (annotated `@final` in 5.1)
* A shutdown whose `shouldShutdown()` or `shutdown()` takes `DateTime`: 6.0 passes `DateTimeInterface`
* Registering Redis/Predis priorities in an order that the 6.0 driver polls differently (6.0 polls the highest number first)
* Handlers that are retried only because they have `canRetry()` and `maxRetry()`, and `RetryTrait` users that override `canRetry()`: 6.0 retries only handlers that implement `RetryableHandlerInterface` or use `RetryTrait`, and honours `canRetry()`
* Creating `LazyRabbitMqDriver` without the `$durable` argument: 6.0 defaults to a durable queue

### Not deprecated

These 6.0 changes have no notice in 5.1 because 5.x cannot detect them or cannot offer the new behaviour without breaking the old one. Read UPGRADE-6.0.md before upgrading:

* RabbitMQ messages are acknowledged after the handler finishes; SQS messages are deleted after handling
* `Dispatcher` catches `Throwable`; driver errors are rethrown instead of being logged
* Changed log and serializer exception messages
* Shutdown timestamps with microseconds and the handling of a shutdown time in the future
* `tracy/tracy` moves to `suggest`; PHP 8.4, `psr/log` and `predis/predis` version requirements
* `readonly` properties and `never` return types
* Redis message ordering, beyond the priority order notice above

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
