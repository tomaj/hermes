---
title: "Delayed reminder"
description: "Schedule a reminder email for later with executeAt on Hermes and Predis."
---

# Delayed reminder

**Goal:** send a reminder email one day before a reservation. The message is emitted when the reservation is made, with `executeAt` set to the reminder time.

<ProjectSource name="reminder" :files="['src/ReminderHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />


<Diagram name="examples-reminder-1" alt="A reservation emits a message with executeAt. Predis adds it to hermes_schedule with ZADD, moves it to the live queue when due, and ReminderHandler emails the customer if the reservation still exists." />

## Files

Redis, Mailpit and `src/Hermes.php` as in the [welcome email tutorial](/examples/welcome-email).

<<< @/../examples/reminder/composer.json{json} [composer.json]

<<< @/../examples/reminder/public/index.php{php} [public/index.php]

<<< @/../examples/reminder/src/ReminderHandler.php{php} [src/ReminderHandler.php]

<<< @/../examples/reminder/bin/worker.php{php} [bin/worker.php]

## Run it

`REMIND_IN` overrides the delay, so you do not have to wait a day:

```bash
composer install
docker compose up -d
php bin/worker.php                    # terminal 1
REMIND_IN=10 php public/index.php     # terminal 2
docker compose exec redis redis-cli zrange hermes_schedule 0 -1 withscores
```

The message waits in `hermes_schedule` with the reminder time as its score. About ten seconds later the worker moves it to the live queue, handles it, and "See you tomorrow" appears in Mailpit at `http://localhost:8025`.

## Pitfalls

- **On 5.x only Predis delays reliably.** `RedisSetDriver` (ext-redis) 5.x writes the sorted set but never moves due messages back ([#95](https://github.com/tomaj/hermes/issues/95), fixed in 6.0). RabbitMQ and SQS ignore `executeAt` and deliver at once. See [Delayed execution](/guide/delayed).
- **Check the state when the message runs.** A day is long: the reservation can be cancelled or moved. Load it again in the handler and do nothing if it no longer applies.
- **A worker must be running.** Due messages move only inside `wait()`. If no worker runs at the reminder time, the reminder is late, not lost.
- **`executeAt` is a Unix timestamp in seconds, as a float.** Compute it in UTC, for example with `strtotime()` or `DateTimeImmutable::getTimestamp()`, so time zones cannot shift it.
- **To change a reminder, do not edit the message.** Emit a new one and let the handler ignore old ones, for example by storing the current reminder id on the reservation.

## Full source

Every file of this recipe, with its `README.md`, `Makefile` and the `e2e.sh` check, is in [`examples/reminder` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/reminder). Clone the repository, `cd examples/reminder`, and run `make install up`, then `make e2e`.

## FAQ

### Can I use ext-redis for reminders?

Not in 5.x. Its promotion step leaves due messages in the sorted set. Use Predis. See [Delayed execution](/guide/delayed).

### What if the reservation is cancelled?

The handler loads it again and does nothing when it no longer applies.

### What if no worker runs at the reminder time?

The reminder runs late, as soon as a worker starts. It is not lost.

### How do I move a reminder?

Emit a new message and make the handler ignore the old one, for example by storing the current reminder id.

### Which unit is `executeAt`?

A Unix timestamp in seconds, as a float.
