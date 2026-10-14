---
title: "Hermes in Laravel"
description: "Use Hermes in Laravel 11 and 12: a service provider, config/hermes.php, an Artisan worker command, PSR-3 logging, hermes:restart, and tests with a mocked EmitterInterface."
howto:
  totalTime: PT20M
  tools: [PHP 8.4, Composer, Laravel 11 or 12, Redis or RabbitMQ or SQS]
---

# Laravel

This page adds Hermes to a fresh Laravel 11 or 12 project. A controller emits `user.registered`, and `php artisan hermes:work` handles it. Read [Framework integration](/frameworks/) first for the parts every framework shares.

<ProjectSource name="frameworks/laravel" :files="['app/Providers/HermesServiceProvider.php', 'config/hermes.php', 'app/Console/Commands/HermesWorkCommand.php', 'app/Http/Controllers/UserController.php']" />

## 1. Install

```bash
composer create-project laravel/laravel app
cd app
composer require tomaj/hermes
composer require predis/predis              # only for the predis driver
composer require php-amqplib/php-amqplib    # only for the rabbitmq driver
composer require aws/aws-sdk-php            # only for the sqs driver
```

Until Hermes 6.0 is tagged, require `tomaj/hermes:dev-master`. The default `redis` driver needs the phpredis extension (`ext-redis`).

## 2. Configure the driver

`config/hermes.php` reads everything from `.env`. Set `HERMES_DRIVER` to `redis`, `predis`, `rabbitmq` or `sqs`, and give each application its own `HERMES_QUEUE`.

<<< @/../examples/frameworks/laravel/config/hermes.php{php} [config/hermes.php]

`app/Hermes/DriverFactory.php` turns that array into a driver. It is the same file in every framework; see [Framework integration](/frameworks/#what-every-integration-has).

<<< @/../examples/frameworks/laravel/app/Hermes/DriverFactory.php{php} [app/Hermes/DriverFactory.php]

Hermes opens its own Redis connection instead of reusing `Redis::connection()`. Laravel's connection adds the `REDIS_PREFIX` to every key, and the Laravel cache and queues share it.

## 3. Register the services

The provider binds four singletons. Producers get `EmitterInterface`; only the worker command resolves `DispatcherInterface`, which is where the handlers are registered.

<<< @/../examples/frameworks/laravel/app/Providers/HermesServiceProvider.php{php} [app/Providers/HermesServiceProvider.php]

Add the provider to `bootstrap/providers.php`:

<<< @/../examples/frameworks/laravel/bootstrap/providers.php{php} [bootstrap/providers.php]

The logger is Laravel's `LoggerInterface` binding, so Hermes writes its `Start handle message` and `End handle message` lines to `storage/logs/laravel.log` with your log channels. See [Logging](/guide/logging).

## 4. Emit from a controller

<<< @/../examples/frameworks/laravel/app/Http/Controllers/UserController.php{php} [app/Http/Controllers/UserController.php]

<<< @/../examples/frameworks/laravel/routes/web.php{php} [routes/web.php]

`routes/web.php` has CSRF protection, so a form must send `@csrf`, and a plain JSON `POST` gets `419`. For a JSON API, put the route in `routes/api.php` (`php artisan install:api`).

A command can emit too. This one is the producer used below:

<<< @/../examples/frameworks/laravel/app/Console/Commands/RegisterUserCommand.php{php} [app/Console/Commands/RegisterUserCommand.php]

## 5. Write a handler

<<< @/../examples/frameworks/laravel/app/Hermes/UserRegisteredHandler.php{php} [app/Hermes/UserRegisteredHandler.php]

The provider builds it with `$app->make()`, so its constructor can ask for any service. Throw to retry, return `false` to give up; see [Retry](/guide/retry).

## 6. Run the worker

Laravel 11 and 12 register every command in `app/Console/Commands`.

<<< @/../examples/frameworks/laravel/app/Console/Commands/HermesWorkCommand.php{php} [app/Console/Commands/HermesWorkCommand.php]

```bash
php artisan hermes:work                          # terminal 1
php artisan app:register-user jane@example.com   # terminal 2
grep 'Welcome email sent' storage/logs/laravel.log
```

`php artisan hermes:work --priority=200` handles only priority 200, if your driver has that priority queue. See [Priority](/guide/priority).

## 7. Restart workers on deploy

<<< @/../examples/frameworks/laravel/app/Console/Commands/HermesRestartCommand.php{php} [app/Console/Commands/HermesRestartCommand.php]

Run `php artisan hermes:restart` as the last deploy step. Each worker stops after its current message, and Supervisor starts a new one with the new code:

<<< @/../examples/frameworks/laravel/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

A worker that starts in the same second as `hermes:restart` stops again at once, because the shutdown time has one-second precision. Supervisor logs it as `not expected` and starts it again. See [Production deployment](/guide/deployment) for systemd and Kubernetes.

## 8. Test the producer

Bind a mock of `EmitterInterface` in the container. From Hermes 6.0 `Emitter` is `final` and cannot be mocked.

<<< @/../examples/frameworks/laravel/tests/Feature/UserControllerTest.php{php} [tests/Feature/UserControllerTest.php]

Test handlers on their own, without a driver; see [Testing](/guide/testing).

## Retry jitter and a `Random\Randomizer` binding

From Hermes 6.0, `Dispatcher` takes an optional `?Random\Randomizer` as its fourth argument (see [Framework integration](/frameworks/#retry-jitter-and-a-registered-random-randomizer)). The provider above calls `new Dispatcher(...)` with three arguments, so a `Random\Randomizer` binding elsewhere in the app does not change the retry times.

If you resolve the class with `app(Dispatcher::class)` instead, Laravel autowires the fourth argument and injects your `Random\Randomizer` binding. Keep `new Dispatcher(...)` in the provider, or add a contextual binding in `register()` so the dispatcher gets `null` and keeps the default:

```php
use Random\Randomizer;
use Tomaj\Hermes\Dispatcher;

$this->app->when(Dispatcher::class)->needs(Randomizer::class)->give(fn () => null);
```

## Full source

Every file, with `install.sh`, `e2e.sh` and the Supervisor program, is in [`examples/frameworks/laravel` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/frameworks/laravel). Verified with Laravel 11.57.0 and 12.70.0 on PHP 8.4, with every driver.

## FAQ

### Why not use Laravel's own queue?

Use it if it fits. Hermes is useful when other PHP apps, for example Nette or Symfony services, emit or handle the same messages, or when you want Hermes priorities and its Redis layout. The two can run side by side on different keys; see [Framework integration](/frameworks/#faq).

### Does `php artisan queue:restart` stop Hermes workers?

No. Laravel's restart signal is in its cache. Hermes workers watch the file of `SharedFileShutdown`, so use `php artisan hermes:restart`. See [Shutdown and max items](/guide/shutdown).

### Can I run the worker with `php artisan schedule:work` or from a job?

No. `hermes:work` blocks until it is told to stop. Run it as its own long-lived process under Supervisor or systemd, as in [Production deployment](/guide/deployment).

### Which Redis client does it use?

`HERMES_DRIVER=redis` uses the phpredis extension, `predis` uses the Predis package. It does not depend on `REDIS_CLIENT`. See [Redis](/guide/drivers/redis) and [Predis](/guide/drivers/predis).

### How do I use SQS locally?

Set `HERMES_DRIVER=sqs`, `HERMES_SQS_ENDPOINT=http://127.0.0.1:4566` and any `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` for a local moto or LocalStack server. See [Amazon SQS](/guide/drivers/sqs).
