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

Every recipe requires `tomaj/hermes:dev-master`, the upcoming 6.0, and PHP 8.4 or newer. The code was run against Redis 8, RabbitMQ 4, and Mailpit.

Each recipe is a runnable project in [`examples/`](https://github.com/tomaj/hermes/tree/master/examples) with a `Makefile` and an `e2e.sh` that checks the whole flow. The code on these pages is imported from those files at build time, so it is the code that the checks run. CI runs every `e2e.sh`, and the framework integrations, against the code of each pull request.

## FAQ

### Are the examples tested?

Yes. The `Examples` workflow runs each recipe's `e2e.sh` (including `filesystem` and `emit-after-commit`, which need no services, and `kafka`) with Redis, RabbitMQ, Kafka and Mailpit where needed, and the [framework integrations](/frameworks/) in fresh Laravel, Symfony and Nette projects with Redis, Predis, RabbitMQ and SQS. It installs Hermes from the pull request through a Composer path repository, so a change that breaks a recipe fails the pull request. [`examples/README.md`](https://github.com/tomaj/hermes/blob/master/examples/README.md#running-the-checks-against-this-checkout) shows how to run the same scripts locally.

### Do the examples use the released package?

Not yet. Until Hermes 6.0 is tagged, each one requires `tomaj/hermes:dev-master` from Packagist with `"minimum-stability": "dev"` and `"prefer-stable": true`, so only Hermes itself comes from a branch. After the release the constraint becomes `^6.0`. If you ran the thumbnails recipe on 5.x, delete its `thumbnails` queue first: 6.0 declares it durable and RabbitMQ rejects the changed declaration.

### Which PHP version do the examples need?

PHP 8.4 or newer, like Hermes 6.0.

### Do I need Docker?

Only for Redis, Mailpit, and RabbitMQ. Any local Redis or RabbitMQ on the default ports works too.

### Why do most examples use Predis?

It needs no PHP extension. On 5.x it was also the only Redis client that promoted delayed messages and retries; from 6.0 ext-redis does too. See [Delayed execution](/guide/delayed).

### Where is the runnable code?

In `examples/` in the repository, with a `Makefile` and an `e2e.sh` per project.
