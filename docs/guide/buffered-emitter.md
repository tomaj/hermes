---
title: "Emitting after a database commit"
description: "BufferedEmitter holds Hermes messages until the database transaction commits and drops them on rollback, so the worker always finds the row. Wiring for Nette, Laravel and Symfony."
---

# Emitting after a database commit

A request inserts a row inside a transaction and emits a message about it. The worker can pick the message up before the transaction commits, look for the row and find nothing. When the transaction rolls back, the message has already left for a row that never existed.

`Tomaj\Hermes\BufferedEmitter` (since 6.0) wraps any `EmitterInterface` and keeps the messages in memory until you call `flush()` from the commit hook. The rollback hook calls `discard()`.

<Diagram name="buffered-emitter-1" alt="BEGIN, INSERT user, emit() keeps the message in the buffer; on commit the hook calls flush() and the worker finds the row, on rollback discard() and nothing is sent" />

```php
use Tomaj\Hermes\BufferedEmitter;
use Tomaj\Hermes\Emitter;

$emitter = new BufferedEmitter(new Emitter($driver, $logger), [$database, 'isInTransaction']);
$database->registerOnCommit([$emitter, 'flush']);
$database->registerOnRollback([$emitter, 'discard']);
```

Producers keep type-hinting `EmitterInterface`; `emit()` returns the `BufferedEmitter`.

## What each method does

| Method | Behaviour |
| --- | --- |
| `__construct(EmitterInterface $emitter, ?callable $shouldBuffer = null)` | `$shouldBuffer` is called on every `emit()` with the message and the priority. `true` buffers, anything else sends right away. `null` always buffers. Closures, `[$object, 'method']` and other callables work. A callable without parameters, such as `$pdo->inTransaction(...)`, is called without arguments, so PHP's own functions do not throw `ArgumentCountError`. |
| `emit($message, $priority)` | Buffers or sends, returns `$this`. |
| `flush()` | Sends the buffer to the inner emitter in the order of `emit()`, each message with its own priority. An empty buffer does not call the inner emitter. |
| `discard(): int` | Drops the buffer without sending and returns how many messages it dropped. |
| `count(): int` | Number of buffered messages; `BufferedEmitter` is `Countable`. |

A message emitted while `flush()` runs, for example by a listener of the inner emitter, is sent by the same `flush()`. A `flush()` called while another one runs returns at once, so nothing is sent twice.

## When `flush()` throws

`flush()` stops at the first message the inner emitter fails to send, usually because the broker is down. The messages before it are sent and stay sent. The failed message and the rest stay in the buffer, `count()` reports them, and `flush()` rethrows the same exception.

The transaction is already committed at that point, so the rows exist and the messages do not. Do not let the exception vanish:

1. Log it, with `count()` if you like.
2. Call `flush()` again. It sends only what is left.
3. When that fails too, call `discard()` and handle the lost messages the way the rest of the app handles a failed side effect: a cron job that finds rows without a processed flag, or an alert.

A buffer that is neither flushed nor discarded is sent by the next successful `flush()` in the same process, after the next commit. In a long-running process (a worker, a queue consumer) that can be much later, so always end with one of the two. Until then the leftover messages are in the wrong place: a message emitted outside a transaction is sent at once and overtakes them, and the next rollback calls `discard()` and drops them although their rows are committed. `discard()` returns how many it dropped; log that number when it is not `0`.

## Not a transactional outbox

The buffer lives in the PHP process. When the process dies between the commit and `flush()` (a fatal error, an OOM kill, a deploy), the messages are lost while the rows are committed. A transactional outbox writes the messages into a table in the same transaction and a separate relay sends them, which survives that. `BufferedEmitter` does not do that. It fixes the more common bug, a worker that is faster than the commit, with one class and no extra table.

## Example

[`examples/emit-after-commit`](https://github.com/tomaj/hermes/tree/master/examples/emit-after-commit) registers users in SQLite, queues the welcome message with `FileSystemDriver` and needs no services. `make e2e` runs a worker while users register and checks that nothing is queued before the commit, that a rolled back registration sends nothing, and that the worker finds every row.

A small PDO wrapper with commit and rollback callbacks:

<<< @/../examples/emit-after-commit/src/Database.php{php} [src/Database.php]

The emitter, with a commit hook that logs a failed `flush()`, retries once and then discards:

<<< @/../examples/emit-after-commit/src/Hermes.php{php} [src/Hermes.php]

The registration, with `fail` as the second argument to roll it back:

<<< @/../examples/emit-after-commit/bin/register.php{php} [bin/register.php]

The handler reads the row the message points to:

<<< @/../examples/emit-after-commit/src/WelcomeHandler.php{php} [src/WelcomeHandler.php]

## Frameworks

- [Nette](/frameworks/nette#emitting-after-a-database-commit): `registerOnCommit()` and `registerOnRollback()` in the service `setup`.
- [Laravel](/frameworks/laravel#emitting-after-a-database-commit): listeners of `TransactionCommitted` and `TransactionRolledBack` that check `transactionLevel()`.
- [Symfony](/frameworks/symfony#emitting-after-a-database-commit): Doctrine DBAL has no commit callbacks, so flush after `transactional()` returns.

## Testing

`BufferedEmitterTest` covers the order and priorities, the predicate, `[$object, 'method']` callables, a partial failure followed by a second `flush()`, messages emitted during `flush()` and a re-entrant `flush()`. `BufferedEmitterIntegrationTest` runs it in front of `Emitter` and `RedisSetDriver`. In your own tests, check `count()` after the code under test and call `flush()` or `discard()` yourself.

## FAQ

### Do I need BufferedEmitter when I do not use transactions?

No. Without a transaction the row is committed before `emit()` runs. With a predicate such as `[$database, 'isInTransaction']` the same emitter sends right away outside a transaction, so you can use it everywhere.

### What happens with nested transactions?

That depends on the hooks. Flush only when the outermost transaction commits, and discard only when the outermost one rolls back. Check how your transaction service calls its callbacks; in Laravel, check `transactionLevel() === 0` in the listener. In Laravel, a message emitted inside a rolled back nested transaction stays in the buffer and is sent when the outer transaction commits.

### Does it work with any driver?

Yes. It wraps an `EmitterInterface`, not a driver, so it works with every driver and with your own emitter.

### Is the buffer shared between requests or workers?

No. Each `BufferedEmitter` instance has its own buffer in memory. Use one instance per container, like `Emitter`.

### Why not extend Emitter?

`Emitter` is `final` from 6.0. Decorating `EmitterInterface` keeps the buffer separate from sending, and it works with any emitter.
