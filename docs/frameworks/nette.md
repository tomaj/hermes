---
title: "Hermes in Nette"
description: "Use Hermes in Nette 3.2: services in a NEON file, Tracy as the PSR-3 logger, a contributte/console worker command, hermes:restart, and tests with a mocked EmitterInterface."
howto:
  totalTime: PT20M
  tools: [PHP 8.4, Composer, Nette 3.2, Redis or RabbitMQ or SQS]
---

# Nette

This page adds Hermes to a fresh `nette/web-project`. A presenter emits `user.registered`, and `php bin/console hermes:work` handles it. Read [Framework integration](/frameworks/) first for the parts every framework shares.

<ProjectSource name="frameworks/nette" :files="['config/hermes.neon', 'app/Bootstrap.php', 'app/Console/HermesWorkCommand.php', 'app/Presentation/User/UserPresenter.php']" />

## 1. Install

```bash
composer create-project nette/web-project app
cd app
composer require tomaj/hermes:dev-master contributte/console
composer require predis/predis              # only for the predis driver
composer require php-amqplib/php-amqplib    # only for the rabbitmq driver
composer require aws/aws-sdk-php            # only for the sqs driver
```

Until Hermes 6.0 is tagged, require `tomaj/hermes:dev-master` as above; switch to `^6.0` once it is released. Nette has no console of its own; [contributte/console](https://github.com/contributte/console) adds Symfony Console commands to the DI container.

## 2. Configure the services

All of Hermes lives in one NEON file. Put the driver and the queue name for each environment in `config/local.neon`, and give each application its own `queue`.

<<< @/../examples/frameworks/nette/config/hermes.neon{yaml} [config/hermes.neon]

`DriverFactory` turns the `hermes` parameter into a driver:

<<< @/../examples/frameworks/nette/app/Hermes/DriverFactory.php{php} [app/Hermes/DriverFactory.php]

`Bootstrap` loads `hermes.neon` and gets a `bootConsoleApplication()` for `bin/console`:

<<< @/../examples/frameworks/nette/app/Bootstrap.php{php} [app/Bootstrap.php]

<<< @/../examples/frameworks/nette/bin/console{php} [bin/console]

## 3. Log through Tracy

`TracyToPsrLoggerAdapter` gives Hermes Tracy's logger as `LoggerInterface`. The `Start handle message` lines go to `log/info.log`. Tracy has no notice level, so the notice `Exiting hermes dispatcher - shutdown` lands in `log/warning.log`. Register a Monolog logger as `LoggerInterface` instead if you want PSR-3 levels as they are. See [Logging](/guide/logging).

## 4. Emit from a presenter

<<< @/../examples/frameworks/nette/app/Presentation/User/UserPresenter.php{php} [app/Presentation/User/UserPresenter.php]

`POST /user/create` with `email` returns `201`, a missing email `422`, and `GET` `405`. The producer command, used below:

<<< @/../examples/frameworks/nette/app/Console/RegisterUserCommand.php{php} [app/Console/RegisterUserCommand.php]

## 5. Write a handler

<<< @/../examples/frameworks/nette/app/Hermes/UserRegisteredHandler.php{php} [app/Hermes/UserRegisteredHandler.php]

Throw to retry, return `false` to give up; see [Retry](/guide/retry).

## 6. Run the worker

<<< @/../examples/frameworks/nette/app/Console/HermesWorkCommand.php{php} [app/Console/HermesWorkCommand.php]

```bash
php bin/console hermes:work                          # terminal 1
php bin/console app:register-user jane@example.com   # terminal 2
grep 'Welcome email sent' log/info.log
```

After you change a NEON file, delete `temp/cache` if the container does not rebuild (it rebuilds on its own in debug mode).

## 7. Restart workers on deploy

<<< @/../examples/frameworks/nette/app/Console/HermesRestartCommand.php{php} [app/Console/HermesRestartCommand.php]

Run `php bin/console hermes:restart` as the last deploy step, after the cache is cleared. Workers stop after the current message, and Supervisor starts new ones:

<<< @/../examples/frameworks/nette/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

The shutdown file is `temp/hermes-shutdown`. If a deploy replaces the `temp` folder, workers in the old release never see the new file; use a path outside the release, such as a shared folder. See [Shutdown and max items](/guide/shutdown) and [Production deployment](/guide/deployment).

## 8. Test the producer

Pass a mock of `EmitterInterface` to the command. From Hermes 6.0 `Emitter` is `final` and cannot be mocked.

<<< @/../examples/frameworks/nette/tests/RegisterUserCommandTest.php{php} [tests/RegisterUserCommandTest.php]

## Retry jitter and a `Random\Randomizer` service

From Hermes 6.0, `Dispatcher` takes an optional `?Random\Randomizer $randomizer` (see [Framework integration](/frameworks/#retry-jitter-and-a-registered-random-randomizer)). Nette autowires it: if the container has a `Random\Randomizer` service, the dispatcher gets it. To keep the default secure engine, on Hermes 6.0 or newer write the dispatcher like this:

```yaml
	hermes.dispatcher:
		create: Tomaj\Hermes\Dispatcher(randomizer: null)
```

On Hermes 5.x Nette fails with `Unable to pass specified arguments to Tomaj\Hermes\Dispatcher::__construct()`, so leave it out there.

## Full source

Every file, with `install.sh`, `e2e.sh` and the Supervisor program, is in [`examples/frameworks/nette` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/frameworks/nette). Verified with nette/application 3.3.0, nette/di 3.2.6, contributte/console 0.11.0 and Tracy 2.12 on PHP 8.4, with every driver.

## FAQ

### Why contributte/console and not a plain PHP script?

The commands get their dependencies from the container, like presenters do, and you get `--priority` and help for free. A plain `bin/worker.php` that builds the container and calls `handle()` works too; see the [welcome email tutorial](/examples/welcome-email).

### Can I use Monolog instead of Tracy?

Yes. Register a `Monolog\Logger` service and remove the `TracyToPsrLoggerAdapter` line, so only one `LoggerInterface` is autowired. See [Logging](/guide/logging).

### Where do I put the driver per environment?

In `config/local.neon`, which `Bootstrap` loads last. For example `parameters: hermes: driver: rabbitmq`. See [Drivers overview](/guide/drivers/overview) to choose one.

### Does the presenter wait for the handler?

No. `emit()` only writes the message to the queue and returns; the worker handles it later. See [Concepts](/guide/concepts).

### How do I run more workers?

Raise `numprocs` in the Supervisor program. Each worker takes one message at a time from the same queue. See [Production deployment](/guide/deployment).
