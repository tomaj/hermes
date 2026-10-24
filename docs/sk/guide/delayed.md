---
title: "Odložené spracovanie"
description: "Spustite správu neskôr pomocou executeAt na Redise, Predise a súborovom systéme a prečo RabbitMQ, SQS a Kafka doručujú hneď."
---

# Odložené spracovanie

Od verzie 2.0 môže správa niesť `executeAt`, časovú značku z `microtime(true)`. Štvrtý argument konštruktora je `created`. Piaty je `executeAt`.

```php
$executeAt = microtime(true) + 60;
$message = new Message('send-email', [
    'to' => 'test@test.com',
    'subject' => 'Later',
    'message' => 'Hello from hermes!',
], executeAt: $executeAt);

$emitter->emit($message);
```

<Diagram name="delayed-1" alt="Driver.send skontroluje executeAt: Redis a Predis vložia budúce správy do množiny naplánovaných a worker ich v čase presunie do živej fronty" />

Podporované drivery sú `RedisSetDriver`, `PredisSetDriver` a `FileSystemDriver`. `LazyRabbitMqDriver::send()`, `AmazonSqsDriver::send()` a `KafkaDriver::send()` `getExecuteAt()` nečítajú.

## Rozšírenie Redis

`send()` správu odloží, keď `getExecuteAt()` nie je `null` a je väčšie ako `microtime(true)`:

```php
$this->redis->zAdd($this->scheduleKey, $message->getExecuteAt(), $serialized);
```

Predvolený kľúč plánovača je `hermes_schedule`. Ak je už obsadený, zmeňte ho štvrtým argumentom konštruktora. Emitter aj worker musia používať ten istý kľúč.

Každá iterácia `wait()` zavolá `zRangeByScore($scheduleKey, '-inf', $now, ['limit' => [0, 1]])`. Pre každý vrátený prvok zavolá `zRem()`. Driver ho pošle znova, len keď `ZREM` oznámi, že prvok odstránil, takže dva workery nikdy nepresunú ten istý záznam. Do verzie 5.x tento krok používal `SPOP`, ktorý na zoradenej množine nefunguje, takže splatné správy zostávali v pláne ([issue #95](https://github.com/tomaj/hermes/issues/95)).

Po úspešnom `ZREM` ju driver deserializuje a zavolá `send()` bez priority. `send()` uvidí to isté `executeAt`. Ak je časová značka stále v budúcnosti, payload sa vráti do zoradenej množiny. Keď je splatná, `send()` ju zapíše do množiny predvolenej priority (`hermes`, ak ste kľúč nezmenili). Pôvodná priorita sa v správe neukladá, takže odložená správa sa do `hermes_high` nevráti.

## Predis

`send()` správu odloží, keď je `getExecuteAt()` pravdivé a väčšie ako teraz. Zápis je `zadd($scheduleKey, [$serialized => $executeAt])`.

`wait()` číta cez `zrangebyscore` s `LIMIT OFFSET 0 COUNT 1`, potom presne tento prvok odstráni cez `zrem`. Cez `send()` ho pošle, len keď `zrem` vráti `1`, takže dva workery nikdy nepresunú ten istý záznam. Do verzie 5.x Predis výsledok `zrem` ignoroval a súbežné workery mohli jednu odloženú správu doručiť dvakrát. Samotné `zrem` je oprava [issue #48](https://github.com/tomaj/hermes/issues/48) z verzie 4.0.1. Presun stále používa `send()` bez priority, takže splatná správa skončí v predvolenej množine.

## FileSystem

`FileSystemDriver::send()` pomenuje súbor podľa `executeAt`, alebo podľa času vytvorenia, keď žiadny nie je. `wait()` číta súbory v poradí názvov a zastaví sa pri prvom, ktorý ešte nie je splatný, takže odložená správa zostane v `new/` vlastného prioritného priečinka dovtedy. Pozrite [FileSystem](/sk/guide/drivers/filesystem#poradie-oneskorenie-a-viaceri-workeri).

## Opakovanie používa to isté pole

`Dispatcher` nastaví `executeAt` z `nextRetry()` a zavolá `send()`. Na Redise, Predise a súborovom systéme opakovanie počká. Na RabbitMQ, SQS a Kafka nie. Vzorec je na stránke [Opakovanie](/sk/guide/retry).

## Časté otázky

### Ako správu odložiť?

Ako piaty argument `Message`, `executeAt`, odovzdajte Unix časovú značku, napríklad `microtime(true) + 3600`. Kompletný príklad je v recepte [odložená pripomienka](/sk/examples/reminder).

### Ktoré drivery podporujú odloženie?

`RedisSetDriver`, `PredisSetDriver` a `FileSystemDriver`. Redis drivery presúvajú splatné správy cez `ZREM`. Vo verzii 5.x ich `RedisSetDriver` nepresúval nikdy ([issue #95](https://github.com/tomaj/hermes/issues/95)), preto tam použite Predis. `FileSystemDriver` kóduje čas splatnosti do názvu súboru. RabbitMQ, SQS a Kafka doručia hneď.

### Kde sú odložené správy uložené?

Na Redise a Predise predvolene v zoradenej množine `hermes_schedule` so skóre podľa `executeAt`. Emitter aj worker musia používať ten istý kľúč plánovača. Na `FileSystemDriver` odložené správy zostanú v `new/` svojho prioritného priečinka, kým nenastane čas splatnosti v názve súboru.

### Ponechá si odložená správa prioritu?

Nie. Keď je splatná, driver zavolá `send()` bez priority, takže skončí s predvolenou prioritou 100.

### Čo ak v čase splatnosti nebeží žiadny worker?

Nič sa nestratí. Splatné správy sa presúvajú len vo `wait()`, takže správa sa spustí hneď, ako sa worker spustí.
