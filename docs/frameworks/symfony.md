---
title: "Hermes in Symfony"
description: "Use Hermes in Symfony 7.4 without Messenger: autowired services in services.yaml, a console worker, Monolog logging, hermes:restart, and tests with a mocked EmitterInterface."
howto:
  totalTime: PT20M
  tools: [PHP 8.4, Composer, Symfony 7.4, Redis or RabbitMQ or SQS]
---

# Symfony

This page adds Hermes to a fresh Symfony 7.4 project, next to or instead of Messenger. A controller emits `user.registered`, and `bin/console hermes:work` handles it. Read [Framework integration](/frameworks/) first for the parts every framework shares.

<ProjectSource name="frameworks/symfony" :files="['config/services.yaml', 'src/Command/HermesWorkCommand.php', 'src/Controller/UserController.php', 'tests/Controller/UserControllerTest.php']" />

## 1. Install

```bash
composer create-project symfony/skeleton:"7.4.*" app
cd app
composer require tomaj/hermes:dev-master symfony/monolog-bundle
composer require predis/predis              # only for the predis driver
composer require php-amqplib/php-amqplib    # only for the rabbitmq driver
composer require aws/aws-sdk-php            # only for the sqs driver
```

Until Hermes 6.0 is tagged, require `tomaj/hermes:dev-master` as above; switch to `^6.0` once it is released. Without `monolog-bundle` the autowired `LoggerInterface` is Symfony's minimal logger, which writes to stderr.

## 2. Configure the services

The whole `config/services.yaml` is below. The `hermes` parameter collects the env vars, the `DriverFactory` builds the driver from it, and the two interfaces are aliases for the Hermes classes. The handler is registered with a `calls` entry on the dispatcher.

<<< @/../examples/frameworks/symfony/config/services.yaml{yaml} [config/services.yaml]

Set `HERMES_DRIVER` to `redis`, `predis`, `rabbitmq` or `sqs` in `.env.local`, and give each application its own `HERMES_QUEUE`.

<<< @/../examples/frameworks/symfony/src/Hermes/DriverFactory.php{php} [src/Hermes/DriverFactory.php]

Monolog is autowired as `LoggerInterface`, so the Hermes lines land in `var/log/dev.log` (or your production handlers). See [Logging](/guide/logging).

## 3. Emit from a controller

<<< @/../examples/frameworks/symfony/src/Controller/UserController.php{php} [src/Controller/UserController.php]

The producer command, used below to try it:

<<< @/../examples/frameworks/symfony/src/Command/RegisterUserCommand.php{php} [src/Command/RegisterUserCommand.php]

## 4. Write a handler

<<< @/../examples/frameworks/symfony/src/Hermes/UserRegisteredHandler.php{php} [src/Hermes/UserRegisteredHandler.php]

It is an autowired service, so its constructor can ask for anything in the container. Throw to retry, return `false` to give up; see [Retry](/guide/retry).

## 5. Run the worker

<<< @/../examples/frameworks/symfony/src/Command/HermesWorkCommand.php{php} [src/Command/HermesWorkCommand.php]

```bash
bin/console hermes:work                          # terminal 1
bin/console app:register-user jane@example.com   # terminal 2
grep 'Welcome email sent' var/log/dev.log
```

`--priority=200` limits the worker to one priority queue; see [Priority](/guide/priority).

## 6. Restart workers on deploy

<<< @/../examples/frameworks/symfony/src/Command/HermesRestartCommand.php{php} [src/Command/HermesRestartCommand.php]

Run `bin/console hermes:restart` after `cache:clear`. Workers stop after the current message, and Supervisor starts new ones:

<<< @/../examples/frameworks/symfony/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

See [Production deployment](/guide/deployment) for systemd.

## 7. Test the producer

Replace `EmitterInterface` in the test container with a mock. From Hermes 6.0 `Emitter` is `final` and cannot be mocked.

<<< @/../examples/frameworks/symfony/tests/Controller/UserControllerTest.php{php} [tests/Controller/UserControllerTest.php]

## Retry jitter and a `Random\Randomizer` service

From Hermes 6.0, `Dispatcher` takes an optional `?Random\Randomizer $randomizer` (see [Framework integration](/frameworks/#retry-jitter-and-a-registered-random-randomizer)). If your app registers a `Random\Randomizer` service, autowiring passes it to the dispatcher, and retry jitter comes from that engine. To keep the default secure engine, add this to the dispatcher service on Hermes 6.0 or newer:

```yaml
    Tomaj\Hermes\DispatcherInterface:
        class: Tomaj\Hermes\Dispatcher
        arguments:
            $randomizer: null
```

On Hermes 5.x the container fails with `has no argument named "$randomizer"`, so leave it out there.

## Full source

Every file, with `install.sh`, `e2e.sh` and the Supervisor program, is in [`examples/frameworks/symfony` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/frameworks/symfony). Verified with Symfony 7.4.21 and monolog-bundle 4.1.0 on PHP 8.4, with every driver.

## FAQ

### Do I need Symfony Messenger?

No. This setup does not install or use Messenger. Both can run in one app if they use different queues; see [Framework integration](/frameworks/#faq).

### Why is the handler registered in `services.yaml` and not with a tag?

One `calls` line per message type is easy to read and needs no compiler pass. With many handlers, a tagged iterator and a small loop in a factory work too. See [Concepts](/guide/concepts).

### The worker does not see my new code after a deploy. Why?

It is a long-running process with the old container in memory. Run `bin/console hermes:restart`. See [Shutdown and max items](/guide/shutdown).

### Can the worker run with `APP_ENV=prod`?

Yes, and it should. Warm the cache before you start it, so that the first worker does not build the container while others wait. See [Production deployment](/guide/deployment).

### How do I point the sqs driver at LocalStack or moto?

Set `HERMES_SQS_ENDPOINT=http://127.0.0.1:4566` and dummy AWS keys. See [Amazon SQS](/guide/drivers/sqs).
