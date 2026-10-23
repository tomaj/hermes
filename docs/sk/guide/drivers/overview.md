---
title: "Prehľad driverov"
description: "Čo robí driver Hermesu medzi Emitterom a Dispatcherom: send, wait, serializácia, priorita, oneskorenie a ako si vybrať."
---

<script setup>
const cards = [
  { title: 'Porovnanie', text: 'Priorita, oneskorenie, perzistencia a potvrdzovanie všetkých šiestich driverov v jednej tabuľke.', link: '/sk/guide/drivers/' },
  { title: 'Redis (ext-redis)', text: 'RedisSetDriver nad rozšírením phpredis. Priority a odložené správy.', link: '/sk/guide/drivers/redis' },
  { title: 'Predis', text: 'PredisSetDriver, čisté PHP. Priority, odložené správy a opakovanie.', link: '/sk/guide/drivers/predis' },
  { title: 'RabbitMQ', text: 'LazyRabbitMqDriver nad php-amqplib. Jedna fronta, bez priorít, bez oneskorenia.', link: '/sk/guide/drivers/rabbitmq' },
  { title: 'Amazon SQS', text: 'AmazonSqsDriver nad AWS SDK. Spravované úložisko, bez priorít, bez oneskorenia.', link: '/sk/guide/drivers/sqs' },
  { title: 'FileSystem', text: 'FileSystemDriver, fronta v lokálnom adresári. Bez brokera, FIFO, priority, odložené správy.', link: '/sk/guide/drivers/filesystem' },
  { title: 'Kafka', text: 'KafkaDriver nad ext-rdkafka. Consumer groups, commit po handleri, bez priorít, bez oneskorenia.', link: '/sk/guide/drivers/kafka' },
  { title: 'Vlastný driver', text: 'Implementujte DriverInterface pre iný broker alebo databázu.', link: '/sk/guide/extending#driver' },
]
</script>

# Prehľad driverov

Driver je jediná časť Hermesu, ktorá komunikuje s frontou. `Emitter` a `Dispatcher` o Redise, RabbitMQ, SQS, Kafka ani lokálnom adresári nič nevedia. Volajú `DriverInterface` a driver to premení na príkazy pre svoj backend.

<Diagram name="drivers-overview-1" alt="Emitter vo webovom procese volá na driveri send(). Driver uloží správu ako JSON do Redisu, RabbitMQ, SQS alebo Kafka. Dispatcher vo workeri volá wait() s callbackom a pre každú správu spustí handler." />

## Čo driver robí

`DriverInterface` má tri metódy:

```php
public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool;
public function setupPriorityQueue(string $name, int $priority): void;
public function wait(Closure $callback, array $priorities): void;
```

- **`send()`** beží vo webovom procese, vo vnútri `Emitter::emit()`. Správu serializuje a zapíše do brokera. Musí byť rýchle, pretože požiadavka naň čaká.
- **`wait()`** beží vo workeri, vo vnútri `Dispatcher::handle()`. Beží v slučke, číta po jednej správe, deserializuje ju a volá `$callback($message, $priority)`. Callback dispatchera nájde handlery pre typ správy a spustí ich.
- **`setupPriorityQueue()`** zaregistruje ďalšiu frontu pre prioritu. Emitter aj každý worker musia zaregistrovať tie isté.

Na oboch stranách používajte tú istú triedu drivera s rovnakými názvami front. Nič iné nezdieľajú.

## Serializácia

Každý driver premení správu na reťazec cez `MessageSerializer`, ktorý zapisuje JSON: id, typ, payload, čas vytvorenia, `executeAt` a počet opakovaní. Payload musí prežiť `json_encode()`. Ak chcete iný formát, zavolajte na driveri `setSerializer()` a ten istý serializér nastavte na oboch stranách. Pozrite [Serializér](/sk/guide/extending#serializer).

Priorita nie je súčasťou JSON. Rozhoduje len o tom, do ktorej fronty `send()` zapíše.

## Priorita, oneskorenie a ukončenie

Drivery sa líšia v tom, čo s nimi robia:

- **Priorita.** `RedisSetDriver` a `PredisSetDriver` majú pre každú prioritu jednu množinu v Redise. `FileSystemDriver` má pre každú prioritu jeden priečinok. `LazyRabbitMqDriver`, `AmazonSqsDriver` a `KafkaDriver` vyhodia v `setupPriorityQueue()` `NotSupportedException`. Pozrite [Priorita](/sk/guide/priority).
- **Oneskorenie.** Redis drivery vložia správu s budúcim `executeAt` do zoradenej množiny a keď je splatná, presunú ju do fronty. Vo verzii 5.x tento presun správne robí len `PredisSetDriver` ([#95](https://github.com/tomaj/hermes/issues/95)). `FileSystemDriver` dá čas splatnosti do názvu súboru a súbory, ktoré ešte nie sú splatné, preskočí. RabbitMQ, SQS a Kafka doručia hneď. Pozrite [Odložené spracovanie](/sk/guide/delayed).
- **Ukončenie a limit správ.** Všetkých šesť driverov používa `ShutdownTrait` a `MaxItemsTrait`. `wait()` kontroluje signál na ukončenie a počet spracovaných správ a workera čisto zastaví. Pozrite [Ukončenie a limit správ](/sk/guide/shutdown).

## Ako si vybrať

- **Už prevádzkujete Redis:** použite `PredisSetDriver`. Nepotrebuje žiadne PHP rozšírenie a v každej verzii funguje s odloženými správami aj opakovaním.
- **Potrebujete priority:** použite `PredisSetDriver`, `RedisSetDriver` alebo `FileSystemDriver`. RabbitMQ, SQS a Kafka majú jednu frontu.
- **Už prevádzkujete RabbitMQ:** použite `LazyRabbitMqDriver`. Oneskorenie a opakovanie si musíte navrhnúť sami.
- **Ste na AWS a chcete spravované úložisko:** použite `AmazonSqsDriver`. Správu zmaže až po dokončení handlera, takže po páde zostane na opätovné doručenie.
- **Už prevádzkujete Kafka:** použite `KafkaDriver`. Workeri v jednej consumer group zdieľajú particie a offset sa commitne po handleri.
- **Jeden server a bez brokera:** použite `FileSystemDriver`. Drží frontu v lokálnom adresári v poradí FIFO a správu spadnutého workera doručí znova.
- **Niečo iné:** napíšte driver. Sú to tri metódy a tri traity.

## Stránky driverov

<LinkCards :items="cards" />

## Vo frameworku

Ako v aplikácii vybrať driver z konfigurácie, nájdete v [Integrácii do frameworkov](/sk/frameworks/). Stránky [Nette](/sk/frameworks/nette), [Laravel](/sk/frameworks/laravel) a [Symfony](/sk/frameworks/symfony) používajú jednu `DriverFactory`, ktorá vytvorí Redis, RabbitMQ a SQS drivery.

## Časté otázky

### Môžu emitter a worker používať rôzne drivery?

Nie. Musia používať tú istú triedu drivera s rovnakými názvami front a prioritnými frontami, pretože fronta je jediné, čo zdieľajú.

### Ktorý driver podporuje odložené správy a opakovanie?

`PredisSetDriver`, od 6.0 aj `RedisSetDriver`, a `FileSystemDriver`. Vo verzii 5.x `RedisSetDriver` zapisuje odložené správy do zoradenej množiny, ale do fronty ich nevracia. RabbitMQ, SQS a Kafka doručia hneď. Pozrite [Odložené spracovanie](/sk/guide/delayed).

### Potvrdí driver správu až po úspechu handlera?

Redis drivery správu odstránia pred spustením handlera, takže worker, ktorý zomrie v `handle()`, ju stratí. RabbitMQ, SQS, Kafka a `FileSystemDriver` ju odstránia až po návrate callbacku. Pozrite riadok Ack v [porovnaní](/sk/guide/drivers/).

### Môžem ukladať správy v inom formáte ako JSON?

Áno. Implementujte `SerializerInterface` a zavolajte `setSerializer()` na driveri vo webovom procese aj v každom workeri. Pozrite [Serializér](/sk/guide/extending#serializer).

### Čo sa stane, keď pošlem správu s prioritou, ktorá nikdy nebola zaregistrovaná?

Redis drivery vyhodia `UnknownPriorityException`. RabbitMQ, SQS a Kafka argument priority ignorujú.
