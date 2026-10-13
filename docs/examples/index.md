---
title: "Examples"
description: "Complete, runnable Hermes recipes: welcome email, invoice PDF, webhooks, CSV import, thumbnails, reminders, and priorities."
---

# Examples

Complete recipes that you can copy into an empty folder and run. Each one has a goal, a diagram, every file, the commands to run and check it, and the pitfalls that matter for that job.

Start with the tutorial. It explains every step, and the other recipes build on its setup.

| Recipe | Driver | Shows |
| --- | --- | --- |
| [Welcome email, from zero](/examples/welcome-email) | Predis | Tutorial: install, emit, handler, worker, Mailpit, retries, tests, Supervisor |
| [Invoice PDF generation](/examples/invoice-pdf) | Predis | An idempotent handler that writes files |
| [Webhook delivery with retries](/examples/webhooks) | Predis | Signed requests, which failures to retry, backoff |
| [CSV import in chunks](/examples/csv-import) | Predis | Splitting a large job into safe, parallel chunks |
| [Image thumbnails](/examples/thumbnails) | RabbitMQ | What changes on a broker without delay or ack |
| [Delayed reminder](/examples/reminder) | Predis | `executeAt` and checking state when the message runs |
| [Priority queues](/examples/priority) | Predis | Priority order, and a worker for urgent work only |

Every recipe uses Hermes 5.x from Packagist (`tomaj/hermes:^5.0`) and PHP 8.2 or newer. The code was run against Redis 8, RabbitMQ 4, and Mailpit.

Each recipe is a runnable project in [`examples/`](https://github.com/tomaj/hermes/tree/master/examples) with a `Makefile` and an `e2e.sh` that checks the whole flow. The code on these pages is imported from those files at build time, so it is the code that the checks run.

## FAQ

### Do the examples use the released package?

Yes. Each one requires `tomaj/hermes:^5.0` from Packagist.

### Which PHP version do the examples need?

PHP 8.2 or newer.

### Do I need Docker?

Only for Redis, Mailpit, and RabbitMQ. Any local Redis or RabbitMQ on the default ports works too.

### Why do most examples use Predis?

It needs no PHP extension and it promotes delayed messages and retries correctly in 5.x. See [Delayed execution](/guide/delayed).

### Where is the runnable code?

In `examples/` in the repository, with a `Makefile` and an `e2e.sh` per project.
