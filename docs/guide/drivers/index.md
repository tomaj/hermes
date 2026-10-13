---
title: "Drivers"
description: "Compare the Hermes drivers for Redis, Predis, RabbitMQ, and Amazon SQS: priority, delay, persistence, and ack."
---

<script setup>
const drivers = ['Redis <code>ext-redis</code>', 'Predis', 'RabbitMQ lazy', 'Amazon SQS']
const comparison = [
  { label: 'Class', cells: ['<code>RedisSetDriver</code>', '<code>PredisSetDriver</code>', '<code>LazyRabbitMqDriver</code>', '<code>AmazonSqsDriver</code>'] },
  { label: 'Priority', cells: ['~ set per priority, order caveat', '~ same as Redis', '✗ throws <code>NotSupportedException</code>', '✗ throws <code>NotSupportedException</code>'] },
  { label: 'Delay', cells: ['✓ sorted set', '✓ sorted set', '✗ published at once', '✗ sent at once'] },
  { label: 'Persistence', cells: ['~ Redis RDB or AOF config', '~ Redis RDB or AOF config', '✓ durable queue', '✓ stored by SQS'] },
  { label: 'Ack', cells: ['✗ removed before the handler', '✗ removed before the handler', '✓ ack after the handler', '✓ deleted after the handler'] },
]
</script>

# Drivers

The four classes in `src/Driver` all implement `DriverInterface`. Pick one for the emitter and the same one for the dispatcher. They must agree on the queue name, the priority keys, and the schedule key, because those strings are the queue.

<Diagram name="drivers-1" alt="Choosing a driver: phpredis gives RedisSetDriver, Predis gives PredisSetDriver, RabbitMQ and SQS have their own drivers" />

## Comparison

<CompareTable :columns="drivers" :rows="comparison" />

✓ supported, ✗ not supported, ~ supported with a caveat. The notes below explain each cell.

### Redis `ext-redis` notes

- **Priority.** Each integer priority is a separate Redis set. The poll order is reverse insertion order, not numeric order. See [issue #50](https://github.com/tomaj/hermes/issues/50).
- **Delay.** A future `executeAt` goes into a sorted set with `ZADD`. Promotion uses `ZREM` on the member it just read. In 5.x it called `SPOP` and never promoted ([#95](https://github.com/tomaj/hermes/issues/95)).
- **Persistence.** Whatever Redis is configured to do (RDB or AOF). Hermes does not set it. Values live in sets plus one sorted set.
- **Ack.** There is no ack API. `SPOP` deletes the member, then the handler runs. A `true` or `false` return is ignored.

### Predis notes

- **Priority.** Same as Redis.
- **Delay.** A future `executeAt` goes into a sorted set. Promotion uses `ZREM` on the member it just read.
- **Persistence.** Same as Redis.
- **Ack.** The same `SPOP` on the live set.

### RabbitMQ lazy notes

- **Priority.** `setupPriorityQueue()` throws `NotSupportedException`. `send()` ignores `$priority`.
- **Delay.** `executeAt` stays in the JSON. `basic_publish` happens immediately.
- **Persistence.** The queue is declared durable and messages are persistent (`delivery_mode` 2). Pass `durable: false` to keep a queue created by 5.x.
- **Ack.** Manual. `basic_ack` after the handler, `basic_nack` with requeue when the callback fails, prefetch 1. 5.x used `no_ack = true` ([#70](https://github.com/tomaj/hermes/issues/70)).

### Amazon SQS notes

- **Priority.** Same as RabbitMQ. The exception text says `AmazonSQS`.
- **Delay.** `executeAt` stays in the JSON. `sendMessage` happens immediately.
- **Persistence.** SQS stores the body until this driver deletes it. The driver deletes after the callback returns. A failed handler leaves the message to reappear after the visibility timeout.
- **Ack.** `deleteMessage` runs after unserialize and the callback succeed. Delivery is at least once. 5.x deleted before the handler ([#69](https://github.com/tomaj/hermes/issues/69)).

Within one Redis or Predis priority, order is not FIFO. A set has no order, and `SPOP` returns one random member.

## Which file to copy

| You have | Start from |
| --- | --- |
| phpredis | [Redis](/guide/drivers/redis), `examples/redis` |
| Predis | [Predis](/guide/drivers/predis), `examples/predis` |
| RabbitMQ | [RabbitMQ](/guide/drivers/rabbitmq), `examples/rabbitmq` |
| AWS SQS | [Amazon SQS](/guide/drivers/sqs), `examples/sqs` |

Shutdown helpers are not drivers. `SharedFileShutdown` works with any driver. `RedisShutdown` needs ext-redis. `PredisShutdown` needs Predis. They are documented on [Shutdown and max items](/guide/shutdown).

## FAQ

### Which driver is the safest default?

`PredisSetDriver`. It needs no extension, supports priorities, and promotes delayed messages and retries. On 5.x it is the only driver that does.

### Which driver keeps messages if a worker crashes?

From 6.0, `LazyRabbitMqDriver` and `AmazonSqsDriver`: they remove the message only after the handler finished. The Redis drivers pop it first. On 5.x none of them did. See [Architecture](/guide/architecture).

### Can the emitter and the worker use different drivers?

No. They must use the same driver class with the same keys, because the keys are the queue.

### Does any driver support acknowledgements?

From 6.0, RabbitMQ acknowledges after the handler and SQS deletes after the handler. Redis pops the member first.

### Is there a ZeroMQ driver?

It moved out of the package in 3.0.0, to [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver).
