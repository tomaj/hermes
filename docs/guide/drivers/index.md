# Drivers

The four classes in `src/Driver` all implement `DriverInterface`. Pick one for the emitter and the same one for the dispatcher. They must agree on the queue name, the priority keys, and the schedule key, because those strings are the queue.

<Diagram name="drivers-1" alt="Drivers diagram" />

## Comparison

| | Redis `ext-redis` | Predis | RabbitMQ lazy | Amazon SQS |
| --- | --- | --- | --- | --- |
| Class | `RedisSetDriver` | `PredisSetDriver` | `LazyRabbitMqDriver` | `AmazonSqsDriver` |
| Priority | Separate Redis set per integer. Poll order is reverse insertion order, not numeric order. [Issue #50](https://github.com/tomaj/hermes/issues/50). | Same as Redis. | `setupPriorityQueue()` throws `NotSupportedException`. `send()` ignores `$priority`. | Same as RabbitMQ. The exception text says `AmazonSQS`. |
| Delay | Future `executeAt` is a sorted set (`ZADD`). Promotion then calls `SPOP` on that key. | Future `executeAt` is a sorted set. Promotion uses `ZREM` on the member it just read. | `executeAt` stays in the JSON. `basic_publish` happens immediately. | `executeAt` stays in the JSON. `sendMessage` happens immediately. |
| Persistence | Whatever Redis is configured to do (RDB or AOF). Hermes does not set it. Values live in sets plus one sorted set. | Same. | `queue_declare` uses `durable = false`. Messages are persistent only when you pass `delivery_mode` in the properties array. A non-durable queue is still dropped with the broker. | SQS stores the body until this driver deletes it. The driver deletes on receive, before `handle()`. |
| Ack | No ack API. `SPOP` deletes the member, then the handler runs. `true` / `false` is ignored. | Same `SPOP` on the live set. | `basic_consume` is called with `no_ack = true`. There is no `basic_ack` or `basic_nack`. | `deleteMessage` runs before unserialize and before the handler. |

Within one Redis or Predis priority, order is not FIFO. A set has no order, and `SPOP` returns one random member.

## Which file to copy

| You have | Start from |
| --- | --- |
| phpredis | [Redis](/guide/drivers/redis), `examples/redis` |
| Predis | [Predis](/guide/drivers/predis), `examples/predis` |
| RabbitMQ | [RabbitMQ](/guide/drivers/rabbitmq), `examples/rabbitmq` |
| AWS SQS | [Amazon SQS](/guide/drivers/sqs), `examples/sqs` |

Shutdown helpers are not drivers. `SharedFileShutdown` works with any driver. `RedisShutdown` needs ext-redis. `PredisShutdown` needs Predis. They are documented on [Shutdown and max items](/guide/shutdown).
