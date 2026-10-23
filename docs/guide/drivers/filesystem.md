---
title: "FileSystem driver"
description: "FileSystemDriver for Hermes: a queue in a local directory with no broker. FIFO order, priorities, delayed messages, at-least-once delivery and a visibility timeout."
---

# FileSystem

`Tomaj\Hermes\Driver\FileSystemDriver` keeps the queue in a local directory. Every message is one JSON file. It needs no broker and no PHP extension, so it fits a single server, a small app, a CLI tool, or a development box where you do not want to run Redis. It supports [priorities](/guide/priority), [delayed messages](/guide/delayed) and retries, and handles messages in FIFO order.

```php
public function __construct(
    string $directory,
    string $queue = 'hermes',
    int $refreshInterval = 1,
    int $visibilityTimeout = 300
)
```

- `$directory` is created on the first `send()` or `wait()` when it is missing. Folders get mode `0775` minus the umask.
- `$queue` is the folder name of the queue for `Dispatcher::DEFAULT_PRIORITY`. Queue names may contain letters, digits, `.`, `_` and `-`, and must not start with `.`. Other names throw `InvalidArgumentException`.
- `$refreshInterval` is how many seconds `wait()` sleeps when no message is due. `0` polls without sleeping.
- `$visibilityTimeout` is how many seconds a claimed message may stay unfinished before it is delivered again. Set it above your slowest handler. `0` never redelivers.

A negative `$refreshInterval` or `$visibilityTimeout` throws `InvalidArgumentException`.

## Emit and handle

This is `examples/filesystem`, a worker that runs report exports. It needs only `composer install`.

<ProjectSource name="filesystem" :files="['src/Hermes.php', 'src/ExportHandler.php', 'bin/emit.php', 'bin/worker.php', 'bin/shutdown.php', 'e2e.sh']" />

Both sides build the driver in one place, so the emitter and the worker agree on the directory and the queue names:

<<< @/../examples/filesystem/src/Hermes.php{php} [src/Hermes.php]

The emitter writes a file and returns:

<<< @/../examples/filesystem/bin/emit.php{php} [bin/emit.php]

The worker uses [`SharedFileShutdown`](/guide/shutdown), which also needs nothing but a file:

<<< @/../examples/filesystem/bin/worker.php{php} [bin/worker.php]

<<< @/../examples/filesystem/bin/shutdown.php{php} [bin/shutdown.php]

## Directory layout

Every queue is a folder in `$directory` with four subfolders:

```text
var/hermes/
├── exports/            the queue for DEFAULT_PRIORITY
│   ├── tmp/            send() writes here first
│   ├── new/            waiting messages
│   ├── processing/     claimed by a worker
│   └── failed/         could not be unserialized
└── exports_high/       one folder per setupPriorityQueue()
```

<Diagram name="drivers-filesystem-1" alt="Message lifecycle: send() writes to tmp/, renames to new/, a worker claims it by renaming to processing/ and deletes it when handled. A failed callback or an expired visibility timeout moves it back to new/; a message that cannot be unserialized moves to failed/." />

`send()` writes the JSON to `tmp/` and renames it into `new/`. A worker therefore never reads a half-written file. When the write or the rename fails, `send()` throws `FileSystemDriverException`.

A worker claims a message by renaming it from `new/` to `processing/`. After the callback returns, the file is deleted. What happens otherwise:

- **The callback throws.** The file goes back to `new/` and the exception stops `wait()`, like the other drivers. See [Retry](/guide/retry) for handler errors, which the dispatcher catches before they reach the driver.
- **The worker dies.** The file stays in `processing/`. The claim renames it to `<claim time>~<name>`, so the claim time is set by the same atomic rename. After `$visibilityTimeout` seconds any worker moves it back to `new/` under its original name.
- **The JSON cannot be unserialized.** The file moves to `failed/` and `wait()` throws `SerializeException`. Inspect or delete those files by hand.

Delivery is at least once. A handler that runs longer than `$visibilityTimeout` can run twice, so make handlers idempotent or raise the timeout.

## Order, delay and several workers

A file name starts with the time the message is due, then a random suffix:

```text
1700000000.250000-3f9c1a2b7d4e5f60.json
```

The time is `executeAt`, or the creation time when there is none. It is zero-padded, so sorting the names sorts by due time. `wait()` reads `new/` in that order, which makes each queue FIFO. It stops at the first file that is not due yet, so a delayed message waits in `new/` without a separate schedule.

<Diagram name="drivers-filesystem-2" alt="Two workers share new/. Both try to rename the oldest due file; worker A wins and moves it to processing/, worker B's rename fails and it takes the next file. A file that is not due yet ends the scan." />

`rename()` is atomic on one local filesystem. When two workers rename the same file, one wins and the other moves on to the next file. You can run as many workers on the directory as you like.

Priority works like the Redis drivers: the highest number is polled first. A worker started with `handle([...])` polls only those priorities and throws `UnknownPriorityException` for a priority that has no queue.

## Limits

- **One server.** `rename()` is only atomic on a local filesystem. Do not share the directory over NFS or another network filesystem. The four folders of a queue are subfolders of one directory, so the rename never crosses filesystems.
- **One file per message.** Tens of thousands of waiting messages make every poll read a large directory. Use [Redis or RabbitMQ](/guide/drivers/) for high volume.
- **Polling.** An idle worker wakes every `$refreshInterval` seconds.
- **Backups.** The queue is plain files. A message in `processing/` is in flight. Do not restore it while workers run.

## FAQ

### When should I use the FileSystem driver?

When the emitter and the worker run on the same server and you do not want to run a broker: a small app, a CLI tool, a cron replacement, or local development. Compare it with the other drivers on [Drivers](/guide/drivers/).

### Can several workers share one directory?

Yes, on one server. Each worker claims a message with an atomic `rename()`, so a message is handled once. Not on NFS, see [Limits](#limits).

### What happens when a worker crashes?

The message stays in `processing/` and goes back to `new/` after `$visibilityTimeout` seconds, so another worker handles it again. See [Directory layout](#directory-layout).

### Are delayed messages and retries supported?

Yes. The file name starts with the due time, and `wait()` skips files that are not due yet. Retries from [`RetryTrait`](/guide/retry) are delayed messages too, so they work without extra setup. See [Delayed execution](/guide/delayed).

### How do I stop workers?

Use [`SharedFileShutdown`](/guide/shutdown) with a file next to the queue, like `examples/filesystem/bin/shutdown.php`. `SIGTERM` also works with `ext-pcntl`.

### Is there a full example?

Yes. [`examples/filesystem`](https://github.com/tomaj/hermes/tree/master/examples/filesystem) runs report exports, and its `make e2e` checks the priority order, a delayed message and the shutdown.
