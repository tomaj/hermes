# Kafka driver

Order confirmations through Apache Kafka with `KafkaDriver`. Needs `ext-rdkafka` 6 (`pecl install rdkafka`, with librdkafka). Docs: <https://tomaj.github.io/hermes/guide/drivers/kafka>.

```bash
make install
make up                      # Kafka 4.3 in KRaft mode on 127.0.0.1:9092
make worker                  # terminal 1
php bin/emit.php 1001        # terminal 2
php bin/shutdown.php         # stops the worker
```

Each handled order appends a line to `var/orders.log`. Start a second `make worker` to share the topic's partitions in the `order-workers` group.

`make e2e` checks that every order is handled once and that a new worker of the group continues after the last committed order.
