---
title: "Drivers overview"
description: "What a Hermes driver does between Emitter and Dispatcher: send, wait, serialization, priority, delay, and how to choose one."
---

<script setup>
const cards = [
  { title: 'Comparison', text: 'Priority, delay, persistence, and ack for all six drivers in one table.', link: '/guide/drivers/' },
  { title: 'Redis (ext-redis)', text: 'RedisSetDriver on the phpredis extension. Priorities and delayed messages.', link: '/guide/drivers/redis' },
  { title: 'Predis', text: 'PredisSetDriver, plain PHP. Priorities, delayed messages, and retries.', link: '/guide/drivers/predis' },
  { title: 'RabbitMQ', text: 'LazyRabbitMqDriver on php-amqplib. One queue, no priorities, no delay.', link: '/guide/drivers/rabbitmq' },
  { title: 'Amazon SQS', text: 'AmazonSqsDriver on the AWS SDK. Managed storage, no priorities, no delay.', link: '/guide/drivers/sqs' },
  { title: 'FileSystem', text: 'FileSystemDriver, a queue in a local directory. No broker, FIFO, priorities, delayed messages.', link: '/guide/drivers/filesystem' },
  { title: 'Kafka', text: 'KafkaDriver on ext-rdkafka. Consumer groups, commit after the handler, no priorities, no delay.', link: '/guide/drivers/kafka' },
  { title: 'Write your own', text: 'Implement DriverInterface for another broker or database.', link: '/guide/extending#a-driver' },
]
</script>

# Drivers overview

A driver is the only part of Hermes that talks to a queue. `Emitter` and `Dispatcher` know nothing about Redis, RabbitMQ, SQS, Kafka, or a local directory. They call a `DriverInterface`, and the driver turns that into commands for its backend.

<Diagram name="drivers-overview-1" alt="The Emitter in the web process calls send() on the driver. The driver stores the message as JSON in Redis, RabbitMQ, SQS, or Kafka. The Dispatcher in the worker calls wait() with a callback and runs the handler for each message." />

## What a driver does

`DriverInterface` has three methods:

```php
public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool;
public function setupPriorityQueue(string $name, int $priority): void;
public function wait(Closure $callback, array $priorities): void;
```

- **`send()`** runs in the web process, inside `Emitter::emit()`. It serializes the message and writes it to the broker. It has to be fast, because the request waits for it.
- **`wait()`** runs in the worker, inside `Dispatcher::handle()`. It loops, reads one message at a time, unserializes it, and calls `$callback($message, $priority)`. The dispatcher's callback finds the handlers for the message type and runs them.
- **`setupPriorityQueue()`** registers an extra queue for a priority. The emitter and every worker must register the same ones.

Use the same driver class, with the same queue names, on both sides. They share nothing else.

## Serialization

Every driver turns a message into a string with `MessageSerializer`, which writes JSON: id, type, payload, created time, `executeAt`, and the retry count. The payload must survive `json_encode()`. Call `setSerializer()` on the driver to use another format, and set the same serializer on both sides. See [A serializer](/guide/extending#a-serializer).

The priority is not part of the JSON. It only decides which queue `send()` writes to.

## Priority, delay, and shutdown

The drivers differ in what they do with these:

- **Priority.** `RedisSetDriver` and `PredisSetDriver` keep one Redis set per priority. `FileSystemDriver` keeps one folder per priority. `LazyRabbitMqDriver`, `AmazonSqsDriver` and `KafkaDriver` throw `NotSupportedException` from `setupPriorityQueue()`. See [Priority](/guide/priority).
- **Delay.** The Redis drivers put a message with a future `executeAt` in a sorted set and move it to the queue when it is due. On 5.x only `PredisSetDriver` does that move correctly ([#95](https://github.com/tomaj/hermes/issues/95)). `FileSystemDriver` puts the due time in the file name and skips files that are not due. RabbitMQ, SQS and Kafka deliver at once. See [Delayed execution](/guide/delayed).
- **Shutdown and max items.** All six drivers use `ShutdownTrait` and `MaxItemsTrait`. `wait()` checks the shutdown signal and the processed count, and stops the worker cleanly. See [Shutdown and max items](/guide/shutdown).

## How to choose

- **You already run Redis:** use `PredisSetDriver`. It needs no PHP extension and works with delayed messages and retries on every version.
- **You need priorities:** use `PredisSetDriver`, `RedisSetDriver` or `FileSystemDriver`. RabbitMQ, SQS and Kafka have one queue.
- **You already run RabbitMQ:** use `LazyRabbitMqDriver`. Delay and retry need your own design.
- **You are on AWS and want managed storage:** use `AmazonSqsDriver`. It deletes the message only after the handler finished, so a crash leaves it for redelivery.
- **You already run Kafka:** use `KafkaDriver`. Workers in one consumer group share the partitions, and the offset is committed after the handler.
- **One server and no broker:** use `FileSystemDriver`. It keeps the queue in a local directory, in FIFO order, and redelivers the message of a crashed worker.
- **Something else:** write a driver. It is three methods and three traits.

## Driver pages

<LinkCards :items="cards" />

## In a framework

To pick the driver from config in an application, see [Framework integration](/frameworks/). The [Nette](/frameworks/nette), [Laravel](/frameworks/laravel) and [Symfony](/frameworks/symfony) pages use one `DriverFactory` that builds the Redis, RabbitMQ and SQS drivers.

## FAQ

### Can the emitter and the worker use different drivers?

No. They must use the same driver class with the same queue names and priority queues, because the queue is the only thing they share.

### Which driver supports delayed messages and retries?

`PredisSetDriver` and, from 6.0, `RedisSetDriver`. In 5.x `RedisSetDriver` writes delayed messages to the sorted set but does not move them back to the queue. RabbitMQ, SQS and Kafka deliver at once. See [Delayed execution](/guide/delayed).

### Does the driver acknowledge a message after the handler succeeds?

The Redis drivers remove the message before the handler runs, so a worker that dies in `handle()` loses it. RabbitMQ, SQS and Kafka remove it only after the callback returns. See the Ack row in the [comparison](/guide/drivers/).

### Can I store messages in something other than JSON?

Yes. Implement `SerializerInterface` and call `setSerializer()` on the driver in the web process and in every worker. See [A serializer](/guide/extending#a-serializer).

### What happens when I send to a priority that was never registered?

The Redis drivers throw `UnknownPriorityException`. RabbitMQ, SQS and Kafka ignore the priority argument.
