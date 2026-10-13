---
layout: home

hero:
  name: Hermes
  text: Background jobs for PHP
  tagline: Emit a message from the request, and let a CLI worker run the slow part. Redis, Predis, RabbitMQ, and Amazon SQS in version 5.x.
  image:
    src: /illustrations/hero-light.svg
    alt: A web app emits a message into a queue that a worker handles
  actions:
    - theme: brand
      text: Getting started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/tomaj/hermes

features:
  - icon:
      src: /icons/queues.svg
      alt: Queue
      width: 42px
      height: 42px
    title: Four built-in drivers
    details: Redis via ext-redis, Predis, lazy RabbitMQ, and Amazon SQS. Each one implements DriverInterface.
  - icon:
      src: /icons/retry.svg
      alt: Retry
      width: 42px
      height: 42px
    title: Retries on failure
    details: RetryTrait schedules another attempt when a handler throws. The delay is real on Redis and Predis.
  - icon:
      src: /icons/priority.svg
      alt: Priority
      width: 42px
      height: 42px
    title: Priority queues
    details: Redis drivers keep a separate set per priority and always poll the highest priority first.
  - icon:
      src: /icons/shutdown.svg
      alt: Shutdown
      width: 42px
      height: 42px
    title: Graceful shutdown
    details: Stop a worker from a shared file or a Redis key. The process manager is what starts it again.
  - icon:
      src: /icons/extend.svg
      alt: Extension point
      width: 42px
      height: 42px
    title: Small extension surface
    details: A driver is send, wait, and setupPriorityQueue. A handler is one handle method.
  - icon:
      src: /icons/workers.svg
      alt: Workers
      width: 42px
      height: 42px
    title: Many workers, one queue
    details: Run the same CLI script on several processes. Redis SPOP and the brokers hand each message to one of them.
---

<div class="home-more">

## Where to go next

- [Getting started](/guide/getting-started) installs 5.x and runs one worker.
- [Examples](/examples/) are complete recipes, starting with a [welcome email tutorial](/examples/welcome-email) from an empty folder.
- [Concepts](/guide/concepts) defines Message, Emitter, Dispatcher, Handler, and Driver.
- [Driver comparison](/guide/drivers/) shows priority, delay, persistence, and ack side by side.
- [API reference](/api/reference) lists the classes that exist in `src/`.

</div>
