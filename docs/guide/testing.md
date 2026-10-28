---
title: "Testing"
description: "Test Hermes handlers without Redis, run the library test suite, coverage, and mutation testing."
---

# Testing

The test suite is PHPUnit 13, configured in `phpunit.xml`. Tests live in `tests/` under `Tomaj\Hermes\Test\`. Risky tests and warnings fail the run.

A handler test does not need Redis. Call `handle()` with a `Message`, or put that message in `DummyDriver` and call `Dispatcher::handle()`. `DummyDriver` lives in `tests/` and is not a class you install.

<Diagram name="testing-1" alt="Testing a handler directly with a new Message, or through DummyDriver and Dispatcher.handle" />

## Commands

`composer.json` scripts are `test` (an alias of `phpunit`), `phpunit`, `phpstan`, `phpcs`, `cs-fix`, `rector`, `rector-fix`, `infection`, `coverage`, and `check`, which runs `phpcs`, `phpstan`, `rector` and `test` in that order. `phpunit` and `test` run without coverage, so they need neither Xdebug nor PCOV; `coverage` and `infection` need one of them.

```bash
composer install
composer phpunit
vendor/bin/phpunit
```

Code style is [PER Coding Style](https://www.php-fig.org/per/coding-style/), checked by PHP-CS-Fixer in dry-run mode over `src`, `tests`, and `examples`. `composer cs-fix` applies the fixes:

```bash
composer phpcs
```

PHPStan runs at level `max` with the settings in `phpstan.neon`: bleeding edge, `phpstan-strict-rules`, `phpstan-deprecation-rules` and `phpstan-phpunit`, plus `checkUninitializedProperties` and `reportUnmatchedIgnoredErrors`. There is no baseline and no ignored error, in `src/` or in `tests/`.

```bash
composer phpstan
```

[Rector](https://getrector.com/) checks `src` and `tests` against the PHP 8.4 set and the dead code, code quality, type declaration and early return sets in `rector.php`. `composer rector` only reports the changes; `composer rector-fix` applies them:

```bash
composer rector
```

## Coverage

```bash
composer coverage
```

It runs PHPUnit with `XDEBUG_MODE=coverage`. Reports:

- HTML: `build/coverage/index.html`
- Clover: `build/logs/clover.xml`

CI runs PHPUnit with the integration tests against real brokers on PHP 8.4, PHP 8.4 with `--prefer-lowest`, PHP 8.5, and a PHP 8.6 nightly build that may fail without failing the workflow. The 95% statement coverage gate runs on the PHP 8.5 job, which also uploads the coverage to Coveralls. That job also uploads the `coverage-report` artifact on pushes to `master`.

Mutation testing uses [Infection](https://infection.github.io/) with `infection.json5`. It fails when the MSI is below 85% or the covered code MSI is below 90%:

```bash
composer infection
```

After a successful CI run on `master`, the documentation workflow publishes that HTML under the site, at [the coverage path](https://tomaj.github.io/hermes/coverage/). The documentation itself is the rest of `https://tomaj.github.io/hermes/`. The two outputs share one GitHub Pages site so a coverage deploy cannot replace the guide.

## What the tests stand in for

`Tomaj\Hermes\Test\Driver\DummyDriver` implements `DriverInterface` in memory. `HandleTest`, `EmitTest`, and the shutdown tests use it so they do not need Redis. It is not part of `src/` and not a driver you install.

`DummyDriver::setupPriorityQueue()` sorts keys numerically before `wait()` reverses them. That is why `HandleTest::testPriorityProcessing()` sees the higher priority first, which matches the Redis drivers from 6.0. On 5.x the Redis drivers did not sort. [Issue #50](https://github.com/tomaj/hermes/issues/50) is the Redis behavior.

Driver tests that need a server mock the client (`Redis`, `Predis\Client`, `SqsClient`, the php-amqplib `AbstractConnection`, and for Kafka the rdkafka stubs). A green PHPUnit run does not require a local Redis, RabbitMQ, Kafka, or AWS account; integration tests skip without the matching env and extension.

## CI workflows

| Workflow | When |
| --- | --- |
| CI | push and pull request: PHPUnit with the integration tests on PHP 8.4, 8.4 lowest, 8.5 and 8.6 (nightly), code style, PHPStan level max, Rector, `composer audit`, and Infection |
| Examples | the examples' end-to-end scripts against this checkout |
| CodeQL Security Analysis | push and pull request to `master`, and a weekly schedule |
| OpenSSF Scorecard | push and pull request to `master`, branch protection changes, and a weekly schedule |
| Deploy Documentation | diagram, link, and SVG checks on pull requests and pushes; Pages deploy after CI succeeds on `master` |

## FAQ

### How do I test a handler?

Call `handle()` directly with a `new Message(...)` and assert the result and side effects. No Redis or worker is needed. The [welcome email tutorial](/examples/welcome-email#_9-test-the-handler) has a complete PHPUnit test.

### Can I install `DummyDriver`?

No. It lives in `tests/` of this repository. Copy the idea, an in-memory `DriverInterface`, into your own test suite.

### Do the Hermes tests need Redis or RabbitMQ?

Not the unit tests: they mock the clients, so `composer phpunit` runs without any server and skips the integration tests. `composer test:integration` runs those against the brokers from `compose.yaml` (`docker compose up -d --wait`).

### Is there a `composer test` script?

Yes. It runs PHPUnit, like `composer phpunit`. `composer check` runs code style, PHPStan, Rector and the tests.

### Does `DummyDriver` order priorities like Redis?

Yes, from 6.0. Both poll the highest priority number first. On 5.x the Redis drivers used reverse registration order. See [Priority](/guide/priority).
