---
title: "Welcome email tutorial"
description: "Step by step from an empty folder: send a welcome email from a PHP background worker with Hermes, Redis, and Mailpit."
howto:
  totalTime: PT15M
  tools: [PHP 8.2 or newer, Composer, Docker with Compose]
---

# Welcome email, from zero

This tutorial builds a small app from an empty folder. A registration form emits a message, a worker picks it up and sends a welcome email, and you watch the email arrive in [Mailpit](https://mailpit.axllent.org/). Then you break SMTP on purpose and watch Hermes retry, and finally you run the worker under Supervisor.

It takes about 15 minutes. Every file is complete, so you can copy each block as it is.

<ProjectSource name="welcome-email" :files="['src/WelcomeEmailHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />


<Diagram name="examples-welcome-email-1" alt="A form posts to index.php, which emits a message into Redis. bin/worker.php takes it and WelcomeEmailHandler sends the email to Mailpit. When sending throws, a retry copy goes back to Redis." />

## What you need

- PHP 8.2 or newer with Composer
- Docker with Compose, for Redis and Mailpit

The app uses `PredisSetDriver`. Predis is a plain PHP package, so there is no extension to install. It is also the Redis driver that moves delayed messages and retries back to the queue with `ZREM`, which the retry step below needs. See [Delayed execution](/guide/delayed).

## 1. Create the project

```bash
mkdir welcome-email && cd welcome-email
mkdir -p public src bin tests deploy
```

<<< @/../examples/welcome-email/composer.json{json} [composer.json]

```bash
composer install
```

This installs Hermes 5.x, Predis, Symfony Mailer for SMTP, and Monolog so the worker prints what it does.

## 2. Start Redis and Mailpit

<<< @/../examples/welcome-email/docker-compose.yml{yaml} [docker-compose.yml]

```bash
docker compose up -d
```

Open `http://localhost:8025`. The Mailpit inbox is empty. Mailpit accepts any email on port 1025 and shows it in the browser, and nothing leaves your machine.

## 3. One place for the driver

The web app and the worker must use the same driver with the same keys. Put the setup in one class.

<<< @/../examples/welcome-email/src/Hermes.php{php} [src/Hermes.php]

`'hermes'` is the queue, `1` is how many seconds the worker sleeps when the queue is empty, and `'hermes_schedule'` is the sorted set for delayed messages and retries.

## 4. Emit from the controller

This is the web side. It validates the form, would save the user, and emits a `user.registered` message. Emitting is one Redis command, so the response is immediate. The email is not sent here.

<<< @/../examples/welcome-email/public/index.php{php} [public/index.php]

## 5. Write the handler

The handler does the slow work in the worker process. `RetryTrait` tells the dispatcher that this handler wants retries, and `maxRetry()` limits them to five.

<<< @/../examples/welcome-email/src/WelcomeEmailHandler.php{php} [src/WelcomeEmailHandler.php]

## 6. Write the worker

The worker registers the handler for the `user.registered` type and calls `handle()`, which loops until it is told to stop.

<<< @/../examples/welcome-email/bin/worker.php{php} [bin/worker.php]

And a small script to stop all workers, used later for deploys:

<<< @/../examples/welcome-email/bin/restart.php{php} [bin/restart.php]

## 7. Run it

Open two terminals in the project folder.

```bash
# terminal 1: the worker
php bin/worker.php
```

```bash
# terminal 2: the web app
php -S localhost:8000 -t public
```

Open `http://localhost:8000`, enter a name and an email address, and press Register. Or from a third terminal:

```bash
curl -d 'name=Jane&email=jane@example.com' http://localhost:8000/
```

The worker prints:

```text
worker.INFO: Start handle message #57e9ddf5-… (user.registered) priority:100
worker.INFO: End handle message #57e9ddf5-… (user.registered)
```

Refresh `http://localhost:8025`. The "Welcome aboard!" email to jane@example.com is there.

## 8. Watch a retry

Stop Mailpit, so SMTP is down, and register again:

```bash
docker compose stop mailpit
curl -d 'name=Bob&email=bob@example.com' http://localhost:8000/
```

The handler throws, and the worker logs it:

```text
worker.ERROR: Handler App\WelcomeEmailHandler throws exception - …
```

Because the handler uses `RetryTrait`, the dispatcher sent a copy of the message with `retries` set to 1 and an `executeAt` 16 to 45 seconds in the future. You can see it waiting in Redis:

```bash
docker compose exec redis redis-cli zrange hermes_schedule 0 -1
```

Start Mailpit again:

```bash
docker compose start mailpit
```

Within a minute the worker logs a second `Start handle message` with the same id and `"retries":1`, and the email to bob@example.com appears in Mailpit. Mailpit keeps messages in memory, so the earlier email is gone after the restart.

The delay grows with each attempt: `retries⁴ + 15 + rand(1, 30) × (retries + 1)` seconds. Five retries cover roughly ten minutes of SMTP downtime. Raise `maxRetry()` for longer outages. See [Retry](/guide/retry).

## 9. Test the handler

A handler is a plain class, so test it without Redis or a worker. Add PHPUnit:

```bash
composer require --dev phpunit/phpunit
```

Add this to `composer.json` and run `composer dump-autoload`:

```json
"autoload-dev": {
    "psr-4": {
        "App\\Tests\\": "tests/"
    }
}
```

<<< @/../examples/welcome-email/tests/WelcomeEmailHandlerTest.php{php} [tests/WelcomeEmailHandlerTest.php]

```bash
vendor/bin/phpunit tests
```

## 10. Run it under Supervisor

In production a process manager keeps the worker running and starts a new one when it exits. The worker exits on purpose in two cases: after 1000 messages (`setMaxProcessItems`), and after `php bin/restart.php`.

<Diagram name="examples-welcome-email-2" alt="Supervisor runs worker_00 and worker_01, both reading Redis. php bin/restart.php sets the hermes_shutdown key." />

<<< @/../examples/welcome-email/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

Copy it into place, with the paths changed to your server:

```bash
sudo cp deploy/hermes-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

```text
hermes-worker:hermes-worker_00   RUNNING   pid 1643852, uptime 0:00:03
hermes-worker:hermes-worker_01   RUNNING   pid 1643858, uptime 0:00:03
```

After each deploy, run:

```bash
php bin/restart.php
```

`PredisShutdown` writes the current time to the `hermes_shutdown` key. Every worker that started before that time finishes its current message, logs `Exiting hermes dispatcher - shutdown`, and exits. Supervisor starts new workers, and they load the new code. Workers that start after the signal ignore it.

With systemd instead of Supervisor, the same worker runs as a template unit:

```ini
# /etc/systemd/system/hermes-worker@.service
[Unit]
Description=Hermes worker %i
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/welcome-email
Environment=REDIS_URL=tcp://127.0.0.1:6379
Environment=MAILER_DSN=smtp://127.0.0.1:1025
ExecStart=/usr/bin/php bin/worker.php
Restart=always
RestartSec=1

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now hermes-worker@1 hermes-worker@2
```

## Pitfalls

- **The message is gone from Redis before the handler runs.** `SPOP` removes it first. If the worker process dies in the middle of `handle()`, that email is not sent and not retried. Retries only happen when the handler throws.
- **Returning `false` does not retry.** Only an exception does. Throw when a later attempt could succeed.
- **The emitter and the worker must agree.** Same driver class, same queue key, same schedule key. `src/Hermes.php` exists so they cannot drift apart.
- **Long-running PHP keeps state.** The worker builds the mailer once. A stale SMTP connection, a cached config value, or a slow memory leak stays until the process exits, which is why `setMaxProcessItems` and Supervisor's restart are worth having.
- **On 5.x retries need Predis.** With `RedisSetDriver` (ext-redis) in 5.x, delayed messages and retries are written to the sorted set but never moved back to the queue. See [Delayed execution](/guide/delayed).
- **Restart after a deploy.** A worker started before the deploy still runs the old code. Run `php bin/restart.php` as the last deploy step.

## Full source

Every file of this recipe, with its `README.md`, `Makefile` and the `e2e.sh` check, is in [`examples/welcome-email` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/welcome-email). Clone the repository, `cd examples/welcome-email`, and run `make install up`, then `make e2e`.

## FAQ

### Why does the email not arrive?

Check that the worker is running and that Mailpit listens on port 1025. The worker log shows `Start handle message` for each message it takes.

### Can I use a real SMTP server instead of Mailpit?

Yes. Set `MAILER_DSN`, for example `smtp://user:pass@smtp.example.com:587`, for the worker.

### Why Predis and not ext-redis?

Retries need the delayed-message path, which only Predis promotes correctly in 5.x. See [Delayed execution](/guide/delayed).

### How many times is a failed email retried?

Five times with this handler's `maxRetry()`, over roughly ten minutes. See [Retry](/guide/retry).

### How do I deploy new code?

Run `php bin/restart.php` after the deploy. Supervisor starts the workers again on the new code. See [Shutdown](/guide/shutdown).
