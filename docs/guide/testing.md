---
title: "Testing"
description: "Test Hermes handlers without Redis, run the library test suite, coverage, and mutation testing."
---

# Testing

The test suite is PHPUnit 13, configured in `phpunit.xml`. Tests live in `tests/` under `Tomaj\Hermes\Test\`. Risky tests and warnings fail the run.

A handler test does not need Redis. Call `handle()` with a `Message`, or put that message in `DummyDriver` and call `Dispatcher::handle()`. `DummyDriver` lives in `tests/` and is not a class you install.

<Diagram name="testing-1" alt="Testing a handler directly with a new Message, or through DummyDriver and Dispatcher.handle" />

## Commands

`composer.json` scripts are `phpunit`, `phpstan`, `phpcs` (also `cs`), `cs-fix`, `infection`, and `coverage`. There is no `composer test` script. The Makefile target `test` runs PHPUnit.

```bash
composer install
make test
```

The same PHPUnit binary:

```bash
composer phpunit
vendor/bin/phpunit
```

Code style is [PER Coding Style](https://www.php-fig.org/per/coding-style/), checked by PHP-CS-Fixer in dry-run mode over `src`, `tests`, and `examples`. `composer cs-fix` applies the fixes:

```bash
composer phpcs
```

PHPStan runs at level `max` with the settings in `phpstan.neon`:

```bash
composer phpstan
```

## Coverage

```bash
composer coverage
make coverage
./coverage.sh
```

All three run PHPUnit with `XDEBUG_MODE=coverage`. Reports:

- HTML: `build/coverage/index.html`
- Clover: `build/logs/clover.xml`

CI runs PHPUnit on PHP 8.4, PHP 8.4 with `--prefer-lowest`, and PHP 8.5. The 65% statement coverage gate runs on the PHP 8.4 job. That job also uploads the `coverage-report` artifact on pushes to `master`.

Mutation testing uses [Infection](https://infection.github.io/) with `infection.json5`:

```bash
composer infection
```

After a successful CI run on `master`, the documentation workflow publishes that HTML under the site, at [the coverage path](https://tomaj.github.io/hermes/coverage/). The documentation itself is the rest of `https://tomaj.github.io/hermes/`. The two outputs share one GitHub Pages site so a coverage deploy cannot replace the guide.

## What the tests stand in for

`Tomaj\Hermes\Test\Driver\DummyDriver` implements `DriverInterface` in memory. `HandleTest`, `EmitTest`, and the shutdown tests use it so they do not need Redis. It is not part of `src/` and not a driver you install.

`DummyDriver::setupPriorityQueue()` sorts keys numerically before `wait()` reverses them. That is why `HandleTest::testPriorityProcessing()` sees the higher priority first. The Redis drivers do not sort. Do not copy that test's expectation onto `RedisSetDriver`. [Issue #50](https://github.com/tomaj/hermes/issues/50) is the Redis behavior.

Driver tests that need a server mock the client (`Redis`, `Predis\Client`, `SqsClient`, `AMQPLazyConnection`). A green PHPUnit run does not require a local Redis, RabbitMQ, or AWS account.

## CI workflows

| Workflow | When |
| --- | --- |
| CI | push and pull request: PHPUnit on PHP 8.4, 8.4 lowest, and 8.5, code style, PHPStan level max, `composer audit`, and Infection |
| CodeQL Security Analysis | push and pull request to `master`, and a weekly schedule |
| OpenSSF Scorecard | push and pull request to `master`, branch protection changes, and a weekly schedule |
| Deploy Documentation | diagram, link, and SVG checks on pull requests and pushes; Pages deploy after CI succeeds on `master` |

## FAQ

### How do I test a handler?

Call `handle()` directly with a `new Message(...)` and assert the result and side effects. No Redis or worker is needed. The [welcome email tutorial](/examples/welcome-email#_9-test-the-handler) has a complete PHPUnit test.

### Can I install `DummyDriver`?

No. It lives in `tests/` of this repository. Copy the idea, an in-memory `DriverInterface`, into your own test suite.

### Do the Hermes tests need Redis or RabbitMQ?

No. Driver tests mock the clients, so `composer phpunit` runs without any server.

### Is there a `composer test` script?

No. Use `composer phpunit` or `make test`.

### Why does `DummyDriver` order priorities differently from Redis?

It sorts priorities numerically. The Redis drivers do not. See [Priority](/guide/priority).
