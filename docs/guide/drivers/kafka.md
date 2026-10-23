---
title: "Kafka driver"
description: "KafkaDriver for Hermes: Apache Kafka through ext-rdkafka. Confirmed sends, consumer groups, commit after the handler, at-least-once delivery."
---

# Kafka

`Tomaj\Hermes\Driver\KafkaDriver` sends messages to an [Apache Kafka](https://kafka.apache.org/) topic and consumes them in a consumer group. It uses [`ext-rdkafka`](https://github.com/arnaud-lb/php-rdkafka) 6, the PHP binding of librdkafka. It is tested against Kafka 4.3 in KRaft mode.

```php
public function __construct(
    RdKafka\Producer $producer,
    ?RdKafka\KafkaConsumer $consumer = null,
    string $topic = 'hermes',
    int $consumeTimeout = 1000,
    int $flushTimeout = 10000
)
```

- `$producer` sends messages. The worker needs one too, because the dispatcher sends retries back to the topic.
- `$consumer` receives messages in `wait()`. The emitter can leave it out. Without it, `wait()` throws `KafkaDriverException`.
- `$consumeTimeout` is how many milliseconds `wait()` blocks for the next message before it checks [shutdown](/guide/shutdown) again.
- `$flushTimeout` is how many milliseconds `send()` waits for Kafka to confirm the message.

An empty topic, or a timeout below 1, throws `InvalidArgumentException`.

## Install

```bash
pecl install rdkafka          # needs librdkafka, e.g. apt install librdkafka-dev
```

`ext-rdkafka` is a `suggest` of `tomaj/hermes`, so add it to your own `composer.json` (`"ext-rdkafka": "^6.0"`). The pure-PHP Kafka clients are not maintained any more, which is why the driver uses the extension. See the [FAQ](#why-ext-rdkafka-and-not-a-pure-php-client).

## Emit and handle

This is `examples/kafka`. It confirms orders, and `make up` starts the same Kafka image as the Hermes tests.

<ProjectSource name="kafka" :files="['src/Hermes.php', 'src/OrderHandler.php', 'bin/emit.php', 'bin/worker.php', 'bin/shutdown.php', 'docker-compose.yml', 'e2e.sh']" />

One class builds the drivers for both sides:

<<< @/../examples/kafka/src/Hermes.php{php} [src/Hermes.php]

The consumer **must** have `enable.auto.commit=false`. Otherwise librdkafka commits messages before the handler finished, and a crash loses them. It also needs a `group.id`.

<<< @/../examples/kafka/bin/emit.php{php} [bin/emit.php]

<<< @/../examples/kafka/bin/worker.php{php} [bin/worker.php]

The worker uses [`SharedFileShutdown`](/guide/shutdown), so `make e2e` can stop it cleanly before it starts a second worker of the same group:

<<< @/../examples/kafka/bin/shutdown.php{php} [bin/shutdown.php]

## Send

<Diagram name="drivers-kafka-1" alt="The emitter produces each message to the topic, keyed by the message id, and waits for the confirmation. Each partition of the topic is consumed by one worker of the consumer group." />

`send()` produces the JSON to the topic, keyed by the message id, then calls `flush($flushTimeout)`. It returns only after Kafka confirmed the message. If Kafka does not confirm in time, it throws `KafkaDriverException` with the librdkafka error code. A send costs one round trip to the broker, which is fine for a web request and a little slow for bulk imports.

`flush()` reports a broker that does not answer in time. A message the broker rejects for good, for example on a topic the client may not write to, also leaves the queue, and only librdkafka's delivery report shows the error. To fail loudly on those, set a delivery report callback on the producer's `Conf` with `setDrMsgCb()` and throw or log when `$message->err` is not `0`.

The key decides the partition. Message ids are random, so messages spread over all partitions. Kafka keeps order only within a partition, so with more than one partition Hermes messages are not FIFO.

## Consume and commit

<Diagram name="drivers-kafka-2" alt="wait() consumes a message, unserializes it, runs the handler and commits the offset. A message that cannot be unserialized is committed and the exception rethrown. When the callback throws, the offset stays uncommitted and the worker stops." />

`wait()` subscribes the consumer to the topic once, then loops. Each loop checks shutdown and max items, then calls `consume($consumeTimeout)`:

- **A message.** The driver unserializes it, runs the callback, and then commits the offset synchronously. Delivery is at least once: a worker that dies before the commit leaves the message for the next worker of the group.
- **The callback throws.** The offset is not committed and the exception stops the worker. The next worker of the group starts at that message again. Handler errors and [retries](/guide/retry) are handled by the dispatcher before they reach the driver.
- **Invalid JSON or an empty value.** The driver commits it and throws `SerializeException`. A message that fails on every delivery would otherwise block its partition for good.
- **Timeout or end of partition.** The driver checks shutdown and consumes again.
- **Any other consumer error.** The driver throws `KafkaDriverException` with the librdkafka message and code.

Workers that use the same `group.id` share the partitions. A topic with three partitions keeps up to three workers busy. More workers than partitions wait idle.

## Limits

- **No priorities.** `setupPriorityQueue()` throws `NotSupportedException`, like RabbitMQ and SQS. `send()` ignores `$priority` and `wait()` ignores `$priorities`. Use one topic and driver per priority if you need lanes. See [Priority](/guide/priority).
- **No delay.** A message with a future `executeAt` is delivered at once, and so are retries. A failing handler retries until `maxRetry()`. See [Delayed execution](/guide/delayed).
- **Commit per message.** Each handled message costs a synchronous commit. For very high throughput, batch outside Hermes.
- **Topic settings.** The driver does not create topics. Kafka creates one on first use when `auto.create.topics.enable` is on. In production, create the topic yourself with the partitions and retention you need.

## FAQ

### Why ext-rdkafka and not a pure-PHP client?

The pure-PHP clients, [`longlang/phpkafka`](https://github.com/swoole/phpkafka) and [`nmred/kafka-php`](https://github.com/weiboad/kafka-php), have had no release since 2023 and 2017. `ext-rdkafka` is maintained and wraps librdkafka, the client that most Kafka tools build on. It is a `suggest`, so you install it only if you use this driver.

### Can several workers consume the same topic?

Yes. Give them the same `group.id` and Kafka assigns each partition to one of them. See [Consume and commit](#consume-and-commit).

### What happens when a worker crashes?

The offset of the message it was handling is not committed, so the next worker of the group gets that message again. Make handlers idempotent. See [Architecture](/guide/architecture).

### Are delayed messages and priorities supported?

No. Kafka delivers in order, at once. Compare the drivers on [Drivers](/guide/drivers/).

### Which Kafka versions are supported?

The integration tests run against Kafka 4.3.1 (`apache/kafka`, KRaft) with ext-rdkafka 6.0.5 and librdkafka 2. librdkafka also talks to older brokers.

### Is there a full example?

Yes. [`examples/kafka`](https://github.com/tomaj/hermes/tree/master/examples/kafka) has a `docker-compose.yml` for Kafka, and its `make e2e` checks that orders are handled once, that `SharedFileShutdown` stops the worker, and that a new worker of the group resumes after the last commit.
