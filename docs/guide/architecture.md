---
title: "Architecture"
description: "How Hermes splits work between the web process and the worker, when a message is removed, and what crosses the queue."
---

# Architecture

Hermes is a library inside your process. The queue is the only thing shared between the request and the worker.

<Diagram name="architecture-1" alt="The controller emits through the Emitter and Driver.send into the broker, and Driver.wait hands messages to the Dispatcher and handler" />

`emit()` returns as soon as `send()` returns. `handle()` is the call that blocks. The handler never runs in the request that emitted the message.

<Diagram name="architecture-2" alt="Sequence of one message from the app through Emitter, Driver, and Dispatcher to the Handler and back" />

## What crosses the process boundary

Only the serialized message. `MessageSerializer` writes JSON with `id`, `type`, `created`, `payload`, `execute_at`, and `retries`. The handler class is not serialized. The worker must register handlers for the types it wants to run.

Priority is not in the JSON. Redis stores it by choosing which set key to `SADD`. After a delayed message is promoted, `send()` is called with the default priority, so the message lands on the default key. The same happens for a retry: `Dispatcher::retryMessage()` calls `send()` with one argument.

## Who removes the message

Every built-in driver removes the message **before** the handler returns.

| Driver | Removal |
| --- | --- |
| `RedisSetDriver`, `PredisSetDriver` | `SPOP` on the set, then the callback |
| `LazyRabbitMqDriver` | `basic_consume` with `no_ack = true` |
| `AmazonSqsDriver` | `deleteMessage`, then unserialize, then the callback |

A handler that returns `false` cannot put that delivery back. A thrown exception can publish a **new** copy when [retry](/guide/retry) is enabled. If the process dies after the pop and before `handle()` finishes, that delivery is gone.

This is at-most-once for a single delivery. It is why a retry copy exists, and why a process manager should restart a worker that exits.

## Life of one message

<Diagram name="architecture-3" alt="Life of one message, from emit to the live queue" />

<Diagram name="architecture-4" alt="Life of one message, from the live queue to the handler result" />

## Several workers

<img class="theme-img only-light" src="/illustrations/workers-light.svg" alt="Three PHP workers connected to one Redis set" />
<img class="theme-img only-dark" src="/illustrations/workers-dark.svg" alt="" />

Start the same script more than once. Redis `SPOP` removes a member, so two workers do not both receive it. RabbitMQ and SQS do the equivalent for their own consumers. The workers do not coordinate with each other inside Hermes. See [Production deployment](/guide/deployment).

## What Hermes does not do

- It does not restart the process. [Shutdown](/guide/shutdown) only makes `handle()` return.
- It does not sort Redis sets. `SPOP` is not FIFO.
- It does not sort priority numbers. [Issue #50](https://github.com/tomaj/hermes/issues/50) describes the order `wait()` actually uses.
- It does not delay RabbitMQ or SQS messages. `executeAt` is stored in the JSON and ignored by those `send()` methods.
- It does not ship a ZeroMQ driver. That driver left the package in 3.0.0 and lives in [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver).

## FAQ

### What crosses from the web process to the worker?

Only the serialized message JSON: id, type, created, payload, execute_at, and retries. Handlers, loggers, and connections are built separately in each process.

### When is a message removed from the broker?

Before the handler finishes, on every built-in driver: `SPOP` on Redis, `no_ack` on RabbitMQ, `deleteMessage` on SQS. A crash during `handle()` loses that delivery.

### Is delivery at-least-once or at-most-once?

At-most-once for a single delivery. A thrown exception can publish a new copy through [Retry](/guide/retry), but a killed process cannot.

### Do several workers coordinate?

No. Each worker pops from the broker, and the broker hands each message to one of them. Start more processes to scale. See [Production deployment](/guide/deployment).

### Does Hermes delay messages on RabbitMQ or SQS?

No. `executeAt` is stored in the JSON and ignored by those drivers, so the message is delivered at once. See [Delayed execution](/guide/delayed).
