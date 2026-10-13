---
title: "Image thumbnails with RabbitMQ"
description: "Create image thumbnails in a Hermes worker on RabbitMQ, and what no_ack and a non-durable queue mean."
---

# Image thumbnails with RabbitMQ

**Goal:** after an upload, make thumbnails in three sizes in a worker. This recipe uses RabbitMQ through `LazyRabbitMqDriver`, so you can see how the broker changes the guarantees.

<Diagram name="examples-thumbnails-1" alt="An uploaded photo is emitted into the RabbitMQ queue thumbnails. ThumbnailHandler receives it with no_ack and writes 160, 640 and 1280 pixel thumbnails with GD." />

<ProjectSource name="thumbnails" :files="['src/ThumbnailHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />

## Files

<<< @/../examples/thumbnails/docker-compose.yml{yaml} [docker-compose.yml]

<<< @/../examples/thumbnails/composer.json{json} [composer.json]

<<< @/../examples/thumbnails/src/Hermes.php{php} [src/Hermes.php]

<<< @/../examples/thumbnails/public/index.php{php} [public/index.php]

<<< @/../examples/thumbnails/src/ThumbnailHandler.php{php} [src/ThumbnailHandler.php]

<<< @/../examples/thumbnails/bin/worker.php{php} [bin/worker.php]

## Run it

```bash
composer install
docker compose up -d
php bin/worker.php                    # terminal 1
php -S localhost:8003 -t public       # terminal 2
curl -F image=@photo.jpg http://localhost:8003/
ls storage/thumbnails
```

Three files appear: `…-160.jpg`, `…-640.jpg`, and `…-1280.jpg`. `make e2e` uploads a generated JPEG and checks the three sizes. The RabbitMQ UI at `http://localhost:15672` (guest / guest) shows the `thumbnails` queue.

To stop the worker after its current message, touch the shared file:

```bash
touch /tmp/hermes-thumbnails-shutdown
```

## Pitfalls

- **Messages are acknowledged on receive.** The driver consumes with `no_ack = true`. If the worker dies while resizing, that upload gets no thumbnails. Keep a way to rebuild them, for example a command that re-emits every image without thumbnails.
- **No delay, so no real retry.** `RetryTrait` works, but the copy is published at once and all attempts happen within seconds. The handler does not use it. A broken file returns `false`, and a full disk throws.
- **The queue is not durable.** `queue_declare` uses `durable = false`, so a RabbitMQ restart drops the queue and its messages. See [RabbitMQ](/guide/drivers/rabbitmq).
- **No priorities.** `setupPriorityQueue()` throws `NotSupportedException` on this driver.
- **GD needs memory for the full image.** A 6000 × 4000 photo needs about 100 MB. Set `memory_limit` for the worker, not for the web app.

## Full source

Every file of this recipe, with its `README.md`, `Makefile` and the `e2e.sh` check, is in [`examples/thumbnails` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/thumbnails). Clone the repository, `cd examples/thumbnails`, and run `make install up`, then `make e2e`.

## FAQ

### What happens if the worker dies while resizing?

That upload gets no thumbnails. RabbitMQ settled the delivery on receive (`no_ack`). Keep a command that re-emits images without thumbnails.

### Why is there no retry?

RabbitMQ ignores `executeAt`, so a retry would run at once. A broken image returns `false` instead.

### Are messages kept when RabbitMQ restarts?

No. The driver declares a non-durable queue. See [RabbitMQ](/guide/drivers/rabbitmq).

### How do I stop the worker?

Touch `/tmp/hermes-thumbnails-shutdown`. `SharedFileShutdown` works with any driver.

### How much memory does GD need?

About width × height × 4 bytes for the decoded image, so roughly 100 MB for a 6000 × 4000 photo.
