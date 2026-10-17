---
title: "Framework integration"
description: "Wire Hermes into Nette, Laravel, or Symfony: one driver from config, EmitterInterface for producers, a hermes:work console worker, logging, shutdown, and tests."
---

# Framework integration

Hermes has no framework bundle, and it does not need one. Its classes take their dependencies in the constructor, so any container can build them. The pages here show the same small integration in three frameworks:

- [Nette 3.2](/frameworks/nette): services in a NEON file, Tracy as the PSR-3 logger, [contributte/console](https://github.com/contributte/console) for the worker command.
- [Laravel 11 and 12](/frameworks/laravel): a service provider, `config/hermes.php`, and Artisan commands.
- [Symfony 7.4](/frameworks/symfony): `services.yaml` with autowiring, Monolog, and Symfony Console commands.

<Diagram name="frameworks-1" alt="A DriverFactory builds one driver from config. The container gives EmitterInterface to controllers and commands, which send messages to Redis, RabbitMQ, or SQS. The hermes:work command gets DispatcherInterface, waits for messages and runs the registered handlers." />

Every example is a set of files in [`examples/frameworks`](https://github.com/tomaj/hermes/tree/master/examples/frameworks), and the code on these pages is imported from those files. Each one was installed into a freshly created project and run against Redis (ext-redis and Predis), RabbitMQ, and SQS (a local moto server).

## What every integration has

| Piece | What it is | Who uses it |
|---|---|---|
| `DriverFactory` | Builds a `DriverInterface` from plain config: `redis` (ext-redis), `predis`, `rabbitmq` or `sqs`, plus the queue name and the max items limit. | the container |
| `EmitterInterface` | A shared `Emitter` with the framework's PSR-3 logger. | controllers, commands, jobs |
| `DispatcherInterface` | A `Dispatcher` with the logger, a shutdown, and the handlers registered with `registerHandler()`. | only the worker command |
| `ShutdownInterface` | `SharedFileShutdown` on a file in the app's temp or storage folder. | the worker and `hermes:restart` |
| `hermes:work` | Calls `$dispatcher->handle()`. Blocks until shutdown or the max items limit. `--priority` limits it to some priorities. | Supervisor or systemd |
| `hermes:restart` | Calls `$shutdown->shutdown()`. Running workers stop after their current message. | the deploy script |
| `app:register-user` | A demo producer that emits `user.registered`. | you, to try it |

The same `DriverFactory` and `UserRegisteredHandler` are used in all three frameworks:

<<< @/../examples/frameworks/symfony/src/Hermes/DriverFactory.php{php} [src/Hermes/DriverFactory.php]

The driver is built once per process. Both the web process and the worker must get the same driver class and queue name; the factory reads them from one config, so they cannot drift apart. See [Drivers overview](/guide/drivers/overview) for what each driver can do.

<<< @/../examples/frameworks/symfony/src/Hermes/UserRegisteredHandler.php{php} [src/Hermes/UserRegisteredHandler.php]

## Rules that hold in every framework

- **Producers depend on `EmitterInterface`, never on `Emitter`.** From Hermes 6.0 the classes are `final`, so PHPUnit cannot mock `Emitter`. Mock the interface; each framework page has a test.
- **Only the worker resolves the dispatcher.** Building it in a web request is harmless, but it is never needed there.
- **Give each application its own queue name.** Two apps that share one Redis and the default `hermes` key take each other's messages. The worker drops a message it has no handler for.
- **Restart workers after a deploy.** A worker keeps the old code until it exits. Run `hermes:restart` as the last deploy step and let the process manager start new workers. See [Shutdown and max items](/guide/shutdown) and [Production deployment](/guide/deployment).
- **Stopping with a signal needs `ext-pcntl`.** With it, `SIGTERM` from Supervisor, systemd or Kubernetes lets the worker finish its message and exit, also inside `hermes:work` (Hermes takes over the console's signal handlers while `handle()` runs). Without it the signal ends PHP at once, even in the middle of a handler, so call `hermes:restart` first. See [Shutdown and max items](/guide/shutdown#sigterm-and-sigint).

## Retry jitter and a registered `Random\Randomizer`

Hermes 6.0 ([#103](https://github.com/tomaj/hermes/pull/103)) adds an optional fourth constructor argument to `Dispatcher`: `?Random\Randomizer $randomizer = null`. Without it, the retry jitter uses a new `Randomizer` with the secure engine. See [Retry](/guide/retry).

Containers that autowire the dispatcher inject a `Random\Randomizer` service if the application registers one for something else, for example a seeded `Mt19937` for fixtures. Retry times then come from that engine. Each framework page shows how to pass `null` instead. Add that line only on Hermes 6.0 or newer: on 5.x and on releases before #103, the argument does not exist and the container refuses the configuration.

The Laravel provider builds the dispatcher with `new Dispatcher(...)`, so it is not affected.

## Verified with

| Framework | Versions | Drivers | Also checked |
|---|---|---|---|
| Nette | nette/web-project, nette/application 3.3.0, nette/di 3.2.6, contributte/console 0.11.0 | ext-redis, Predis 3.7, RabbitMQ (php-amqplib 3.7.5), SQS on moto | POST to the presenter, the PHPUnit test, Supervisor |
| Laravel | 11.57.0 and 12.70.0 | the same four | feature tests, Supervisor |
| Symfony | 7.4.21, monolog-bundle 4.1.0 | the same four | POST to the controller, the WebTestCase test, Supervisor |

PHP 8.4, Hermes `dev-master`. Each framework was also run on Hermes 5.0.0 and on the branches of [#103](https://github.com/tomaj/hermes/pull/103) and [#105](https://github.com/tomaj/hermes/pull/105).

## FAQ

### Is there a Hermes bundle or package for my framework?

No. The integration is a factory, a few service definitions and two console commands, shown on the [Nette](/frameworks/nette), [Laravel](/frameworks/laravel) and [Symfony](/frameworks/symfony) pages. Copy them and change what you need.

### Can I use Hermes next to Laravel queues or Symfony Messenger?

Yes. Hermes does not touch them. Use a different Redis key or RabbitMQ queue for Hermes, so the two never read each other's messages.

### Which driver should I configure?

Predis or ext-redis if you want priorities and delayed messages, RabbitMQ or SQS if you already run them. The [comparison](/guide/drivers/) lists what each driver supports.

### Do I need one worker per handler?

No. One `hermes:work` process runs every handler registered on the dispatcher. Run more processes for more throughput, and use `--priority` to keep some for urgent messages. See [Priority](/guide/priority).

### How do I test code that emits messages?

Type-hint `EmitterInterface` and replace it with a PHPUnit mock in the test. Each framework page has a working test, and [Testing](/guide/testing) covers the handlers.
