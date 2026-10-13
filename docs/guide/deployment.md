# Production deployment

Run the worker as a CLI process that is allowed to block. Keep the web nodes on `Emitter` only. Share the queue, not the PHP objects.

<Diagram name="deployment-1" alt="Production deployment diagram" />

<Diagram name="deployment-2" alt="Production deployment diagram" />

<img class="theme-img only-light" src="/illustrations/workers-light.svg" alt="One Redis set with three worker processes" />
<img class="theme-img only-dark" src="/illustrations/workers-dark.svg" alt="" />

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

Before a restart, run a one-shot `php /var/www/shutdown.php` that calls `RedisShutdown::shutdown()`, or touch the file if you use `SharedFileShutdown`. `stopwaitsecs` should be longer than one message. Supervisor then sends `SIGTERM`. Hermes does not install a `pcntl` signal handler. If the process is killed mid-handler, the message was already popped.

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

[Install]
WantedBy=multi-user.target
```

`Restart=always` covers a clean exit from max items and a clean exit from shutdown. Use a template unit (`hermes@.service`) or several services when you want separate priority filters.

## Kubernetes

A Deployment with three replicas is three workers. `preStop` publishes the shutdown key so the old pod can finish the message it already took and then return from `handle()`. `terminationGracePeriodSeconds` has to cover that.

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
- The process manager restarts on exit code 0, because max items and shutdown both return normally.
- RabbitMQ queues from this library are non-durable unless you declare them yourself.
- SQS credentials can `createQueue`, because the driver constructor does.
- Logs go to a PSR-3 logger. `EchoHandler` stdout is only the two lines it `echo`s.
