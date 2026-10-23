---
title: "Architektúra"
description: "Ako Hermes delí prácu medzi webový proces a workera, kedy sa správa odstráni a čo prechádza cez frontu."
---

# Architektúra

Hermes je knižnica vo vašom procese. Fronta je jediné, čo požiadavka a worker zdieľajú.

<Diagram name="architecture-1" alt="Controller vloží správu cez Emitter a Driver.send do brokera a Driver.wait podáva správy Dispatcheru a handleru" />

`emit()` sa vráti hneď, ako sa vráti `send()`. Blokuje volanie `handle()`. Handler nikdy nebeží v požiadavke, ktorá správu vložila.

<Diagram name="architecture-2" alt="Postup jednej správy z aplikácie cez Emitter, Driver a Dispatcher k Handleru a späť" />

## Čo prechádza hranicou procesu

Len serializovaná správa. `MessageSerializer` zapisuje JSON s `id`, `type`, `created`, `payload`, `execute_at` a `retries`. Trieda handlera sa neserializuje. Worker musí zaregistrovať handlery pre typy, ktoré chce spúšťať.

Priorita v JSON nie je. Redis ju uloží výberom kľúča množiny, do ktorej robí `SADD`. Keď sa odložená správa presunie do fronty, `send()` sa zavolá s predvolenou prioritou, takže správa skončí na predvolenom kľúči. To isté platí pre opakovanie: `Dispatcher::retryMessage()` volá `send()` s jedným argumentom.

## Kto správu odstráni

Redis drivery správu odstránia **pred** spustením handlera. Od 6.0 ju RabbitMQ, SQS, Kafka a `FileSystemDriver` odstránia až **po** návrate callbacku.

| Driver | Odstránenie |
| --- | --- |
| `RedisSetDriver`, `PredisSetDriver` | `SPOP` z množiny, potom callback |
| `LazyRabbitMqDriver` | `basic_ack` po callbacku (6.0+) |
| `AmazonSqsDriver` | deserializácia, callback, potom `deleteMessage` (6.0+) |
| `KafkaDriver` | callback, potom `commit` offsetu (6.0+) |
| `FileSystemDriver` | premenovanie do `processing/`, potom zmazanie po callbacku (6.0+) |

Handler, ktorý vráti `false`, nemôže doručenie vrátiť späť. Vyhodená výnimka môže publikovať **novú** kópiu, keď je zapnuté [opakovanie](/sk/guide/retry). Ak proces zomrie po vybratí a pred dokončením `handle()`, toto doručenie je stratené.

Na Redise a Predise ide pri jednom doručení o najviac jedno doručenie (at-most-once). Na RabbitMQ, SQS, Kafka a `FileSystemDriver` (6.0+) o aspoň jedno doručenie (at-least-once). V oboch prípadoch má workera, ktorý skončí, reštartovať správca procesov.

## Život jednej správy

<Diagram name="architecture-3" alt="Život jednej správy od vloženia po živú frontu" />

<Diagram name="architecture-4" alt="Život jednej správy od živej fronty po výsledok handlera" />

## Viac workerov

<img class="theme-img only-light" src="/illustrations/workers-light.svg" alt="Tri PHP workery pripojené k jednej množine v Redise" />
<img class="theme-img only-dark" src="/illustrations/workers-dark.svg" alt="" />

Spustite ten istý skript viackrát. Redis `SPOP` odstráni prvok, takže ho nedostanú dvaja workery. RabbitMQ, SQS, Kafka a `FileSystemDriver` robia to isté pre svojich konzumentov. Workery sa v rámci Hermesu navzájom nekoordinujú. Pozrite [Nasadenie do produkcie](/sk/guide/deployment).

## Čo Hermes nerobí

- Nereštartuje proces. [Ukončenie](/sk/guide/shutdown) len spôsobí, že sa `handle()` vráti.
- Neusporadúva množiny v Redise. `SPOP` nie je FIFO.
- Prioritné fronty číta od najvyššieho čísla nadol. Verzia 5.x používala opačné poradie registrácie ([issue #50](https://github.com/tomaj/hermes/issues/50)).
- Neodkladá správy na RabbitMQ, SQS ani Kafka. `executeAt` sa uloží do JSON a metódy `send()` týchto driverov ho ignorujú.
- Nedodáva ZeroMQ driver. Ten z balíka odišiel vo verzii 3.0.0 a je v [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver).

## Časté otázky

### Čo prechádza z webového procesu do workera?

Len JSON serializovanej správy: id, type, created, payload, execute_at a retries. Handlery, loggery a pripojenia si každý proces vytvára sám.

### Kedy sa správa z brokera odstráni?

Na Redise (`SPOP`) pred spustením handlera, takže pád počas `handle()` toto doručenie stratí. Na RabbitMQ (`basic_ack`), SQS (`deleteMessage`), Kafka (`commit`) a `FileSystemDriver` (zmazanie po callbacku) od 6.0 až po návrate callbacku; po páde ju broker doručí znova ([#70](https://github.com/tomaj/hermes/issues/70), [#69](https://github.com/tomaj/hermes/issues/69)).

### Je doručenie at-least-once alebo at-most-once?

Závisí od drivera. Redis a Predis sú at-most-once: zabitý proces doručenie stratí a novú kópiu môže publikovať len vyhodená výnimka cez [Opakovanie](/sk/guide/retry). RabbitMQ, SQS, Kafka a `FileSystemDriver` sú od 6.0 at-least-once: nedokončená správa sa doručí znova, preto majú byť handlery idempotentné.

### Koordinujú sa viaceré workery?

Nie. Každý worker vyberá z brokera a broker dá každú správu len jednému z nich. Na škálovanie spustite viac procesov. Pozrite [Nasadenie do produkcie](/sk/guide/deployment).

### Odkladá Hermes správy na RabbitMQ, SQS alebo Kafka?

Nie. `executeAt` sa uloží do JSON a tieto drivery ho ignorujú, takže správa sa doručí hneď. Pozrite [Odložené spracovanie](/sk/guide/delayed).
