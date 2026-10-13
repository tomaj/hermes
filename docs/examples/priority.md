---
title: "Priority queues"
description: "Send urgent emails before newsletters with Hermes priority queues on Redis, and keep a worker for urgent work."
---

# Priority queues

**Goal:** a password reset email goes out before a receipt, and a receipt before a newsletter, even when thousands of newsletters are waiting. One worker can also be kept free for urgent work only.

<Diagram name="examples-priority-1" alt="The Emitter writes to hermes_high 200, hermes_normal 150 and hermes 100. bin/worker.php high reads only 200. bin/worker.php reads high, then normal, then low." />

<ProjectSource name="priority" :files="['src/PrintHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />

## Files

The project starts Redis from its own `docker-compose.yml`, as in the [welcome email tutorial](/examples/welcome-email). This recipe has its own `src/Hermes.php`, because both sides must register the same priority queues.

<<< @/../examples/priority/composer.json{json} [composer.json]

<<< @/../examples/priority/src/Hermes.php{php} [src/Hermes.php]

<<< @/../examples/priority/public/index.php{php} [public/index.php]

<<< @/../examples/priority/src/PrintHandler.php{php} [src/PrintHandler.php]

<<< @/../examples/priority/bin/worker.php{php} [bin/worker.php]

## Run it

Emit first and start the worker afterwards, so all five messages wait in Redis and you can see the order:

```bash
composer install
docker compose up -d redis
php public/index.php
php bin/worker.php
```

```text
[09:16:53] email.password_reset {"email":"jane@example.com"}
[09:16:53] email.receipt {"order_id":1042}
[09:16:53] email.newsletter {"subscriber_id":2}
[09:16:53] email.newsletter {"subscriber_id":3}
[09:16:53] email.newsletter {"subscriber_id":1}
```

Now run a worker for urgent work only:

```bash
php public/index.php
php bin/worker.php high
```

It handles the password reset and leaves the other four in Redis for a normal worker. `make e2e` checks both runs.

## Pitfalls

- **Register queues in ascending order.** The worker polls queues in reverse registration order, not by number. The driver always registers the default priority 100 first. This recipe therefore uses 100 as the lowest priority and adds 150 and 200 in that order. A priority below 100 would be polled before 100. See [Priority](/guide/priority) and issue [#50](https://github.com/tomaj/hermes/issues/50).
- **Emitter and worker need the same queues.** Emitting with a priority that the driver did not register throws `UnknownPriorityException`.
- **No order inside one priority.** The three newsletters come out in random order, because a Redis set has none.
- **Retries go to the default priority.** The dispatcher sends a retry copy without a priority, so a failed password reset is retried at 100, behind receipts.
- **Low priority can wait forever.** While high messages keep arriving, a worker that reads all queues never reaches the low ones. Add a worker for low work if newsletters must go out.
- **Only the Redis drivers have priorities.** On RabbitMQ and SQS, `setupPriorityQueue()` throws `NotSupportedException`. Use separate queues and workers there.

## Full source

Every file of this recipe, with its `README.md`, `Makefile` and the `e2e.sh` check, is in [`examples/priority` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/priority). Clone the repository, `cd examples/priority`, and run `make install up`, then `make e2e`.

## FAQ

### Why is the lowest priority 100?

The driver always registers 100 first and polls in reverse registration order, so a priority below 100 would run before it.

### Why do newsletters come out in random order?

Each priority is a Redis set, which has no order.

### Where do retries land?

On the default priority 100, because the dispatcher sends retry copies without a priority.

### How do I keep a worker free for urgent work?

Start it with `php bin/worker.php high`, which calls `handle([200])`.

### Can RabbitMQ or SQS do this?

No. Use separate queues and separate workers there.
