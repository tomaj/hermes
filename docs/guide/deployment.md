---
title: "Production deployment"
description: "Run Hermes workers in production with Supervisor, systemd, or Kubernetes, scale them, and restart them on deploy."
---

# Production deployment

Run the worker as a CLI process that is allowed to block. Keep the web nodes on `Emitter` only. Share the queue, not the PHP objects.

<DiagramTabs :tabs="[
  { label: 'Scaling', name: 'deployment-2', alt: 'Two web replicas emit into Redis, RabbitMQ, or SQS, and three worker replicas take messages from it' },
  { label: 'Restart on deploy', name: 'deployment-1', alt: 'Web replicas emit into one broker. Supervisor, systemd template units, or Kubernetes replicas run the workers, and a shutdown signal makes the manager start the new process' },
]" />

Each worker is the same script. Redis `SPOP` gives a member to one connection. RabbitMQ and SQS do that inside the broker. Hermes does not elect a leader.

Scale by adding processes. A worker pinned to one priority with `handle([110])` can have a different replica count from a worker pinned to `100`. See [Priority](/guide/priority).

Recycle with `setMaxProcessItems()` so a process does not run for weeks. Recycle on deploy with a [shutdown](/guide/shutdown) signal, then let the process manager start the new code.

The snippets below use a Redis worker. Swap the bootstrap for Predis, RabbitMQ, or SQS. The process manager does not change.

```php
<?php

declare(strict_types=1);

use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Shutdown\RedisShutdown;

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', 6379);

$driver = new RedisSetDriver($redis, 'hermes', 1);
$driver->setMaxProcessItems(1000);

$shutdown = new RedisShutdown($redis);
$dispatcher = new Dispatcher($driver, null, $shutdown);
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`getenv()` is your configuration, not a Hermes API. The `RedisSetDriver` arguments are the real constructor: client, key, refresh interval.

## Supervisor

[Supervisor](https://supervisord.org/) starts four copies and restarts them when `handle()` returns.

```ini
[program:hermes]
command=php /var/www/worker.php
directory=/var/www
user=www-data
autostart=true
autorestart=true
numprocs=4
process_name=%(program_name)s_%(process_num)02d
stdout_logfile=/var/log/hermes.log
stopwaitsecs=30
```

`supervisorctl stop` and `restart` send `SIGTERM` (`stopsignal`, default `TERM`). With `ext-pcntl` the worker finishes the current message and exits ([Shutdown](/guide/shutdown#sigterm-and-sigint)). After `stopwaitsecs` Supervisor sends `SIGKILL`, so set it longer than your slowest message. With the Redis drivers a message killed mid-handler is lost, because it was already popped. Without `ext-pcntl`, run a one-shot `php /var/www/shutdown.php` that calls `RedisShutdown::shutdown()` (or touch the file of `SharedFileShutdown`) before the restart.

## systemd

```ini
[Unit]
Description=Hermes worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www
ExecStart=/usr/bin/php /var/www/worker.php
Restart=always
RestartSec=1
# SIGTERM first; SIGKILL after TimeoutStopSec. Longer than the slowest message.
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

`systemctl stop` sends `SIGTERM`; with `ext-pcntl` the worker finishes the current message and exits. `TimeoutStopSec` is how long systemd waits before `SIGKILL`. The default `KillMode=control-group` sends `SIGTERM` to every process of the unit too, so a child process a handler started gets it at the same time. `Restart=always` covers a clean exit from max items and a clean exit from shutdown. Use a template unit (`hermes@.service`) or several services when you want separate priority filters.

## Kubernetes

A Deployment with three replicas is three workers. When a pod stops, Kubernetes runs `preStop`, then sends `SIGTERM` to the container's main process, and `SIGKILL` once `terminationGracePeriodSeconds` (default 30) has passed since the start of termination. With `ext-pcntl` the `SIGTERM` alone makes the worker finish its message and exit, so `preStop` is optional. Run PHP as the main process (`command: ["php", ...]`, or `exec` from a shell entrypoint), otherwise the signal goes to the shell and not to PHP. `terminationGracePeriodSeconds` has to cover `preStop` plus the slowest message. Without `ext-pcntl`, `preStop` publishes the shutdown key so the old pod can finish the message it already took and then return from `handle()`.

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: hermes
spec:
  replicas: 3
  selector:
    matchLabels:
      app: hermes
  template:
    metadata:
      labels:
        app: hermes
    spec:
      terminationGracePeriodSeconds: 60
      containers:
        - name: worker
          image: example.invalid/app:5
          command: ["php", "worker.php"]
          env:
            - name: REDIS_HOST
              value: redis
          lifecycle:
            preStop:
              exec:
                command: ["php", "shutdown.php"]
```

`example.invalid` is not a registry. The command list is the same `php worker.php` as the other process managers. `shutdown.php` should call `shutdown()` on the same `RedisShutdown` key the worker polls. A new replica starts after the timestamp and keeps running, because `shouldShutdown()` is false when the stored time is older than the process start.

Readiness probes do not fit a process that never opens a port. Leave the worker without an HTTP probe. Liveness can be a process check. Killing a live worker drops the message that was already removed from the queue.

## Deploy checklist

- Emitter and worker use the same driver, key, schedule key, and priority map.
- `handle()` is not on the web path.
- The process manager restarts on any exit code: max items and shutdown return normally (status 0), and a driver error is logged and rethrown (non-zero status).
- RabbitMQ queues from this library are durable from 6.0. A queue created by 5.x is non-durable; recreate it or pass `durable: false` on emitters and workers (see [RabbitMQ](/guide/drivers/rabbitmq#upgrading-a-5-x-queue)).
- SQS credentials can `createQueue`, because the driver constructor does.
- Logs go to a PSR-3 logger. `EchoHandler` stdout is only the two lines it `echo`s.

## FAQ

### How many workers should I run?

Start with one per CPU core for CPU-bound handlers, more for I/O-bound ones such as email or HTTP. Add processes until the queue stays short.

### Supervisor, systemd, or Kubernetes?

Any of them works. The worker is a plain CLI script that exits on shutdown or max items, and the manager starts it again. Pick the one you already run.

### How do I deploy new code without losing messages?

Let the process manager stop the workers with `SIGTERM` (needs `ext-pcntl`), or run the shutdown signal as the last deploy step. Either way workers finish their current message, exit, and the manager starts them on the new code. See [Shutdown](/guide/shutdown).

### Do workers need a health check port?

No. A worker opens no port. Use a process liveness check, not an HTTP readiness probe.

### Should web servers run workers too?

They can, but keep `handle()` out of the web path. Web nodes only need `Emitter`.
