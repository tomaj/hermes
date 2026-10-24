---
title: "Drivery"
description: "Porovnanie driverov Hermesu pre Redis, Predis, RabbitMQ, Amazon SQS, Kafka a súborový systém: priorita, oneskorenie, perzistencia a potvrdzovanie."
---

<script setup>
const drivers = ['Redis <code>ext-redis</code>', 'Predis', 'RabbitMQ lazy', 'Amazon SQS', 'FileSystem', 'Kafka']
const comparison = [
  { label: 'Trieda', cells: ['<code>RedisSetDriver</code>', '<code>PredisSetDriver</code>', '<code>LazyRabbitMqDriver</code>', '<code>AmazonSqsDriver</code>', '<code>FileSystemDriver</code>', '<code>KafkaDriver</code>'] },
  { label: 'Priorita', cells: ['✓ množina pre každú prioritu', '✓ množina pre každú prioritu', '✗ vyhodí <code>NotSupportedException</code>', '✗ vyhodí <code>NotSupportedException</code>', '✓ priečinok pre každú prioritu', '✗ vyhodí <code>NotSupportedException</code>'] },
  { label: 'Oneskorenie', cells: ['✓ zoradená množina', '✓ zoradená množina', '✗ publikuje sa hneď', '✗ odošle sa hneď', '✓ čas splatnosti v názve súboru', '✗ produkuje sa hneď'] },
  { label: 'Perzistencia', cells: ['~ podľa nastavenia RDB alebo AOF v Redise', '~ podľa nastavenia RDB alebo AOF v Redise', '✓ durable fronta', '✓ ukladá SQS', '~ súbory na jednom lokálnom disku', '✓ retencia topicu'] },
  { label: 'Ack', cells: ['✗ odstráni sa pred handlerom', '✗ odstráni sa pred handlerom', '✓ ack po handleri', '✓ zmaže sa po handleri', '✓ zmaže sa po handleri', '✓ offset sa commitne po handleri'] },
]
</script>

# Drivery

Šesť tried v `src/Driver` implementuje `DriverInterface`. Vyberte jednu pre emitter a tú istú pre dispatcher. Musia sa zhodovať v názve fronty, kľúčoch priorít a kľúči plánovača, pretože tieto reťazce sú samotná fronta.

<Diagram name="drivers-1" alt="Výber drivera: phpredis znamená RedisSetDriver, Predis znamená PredisSetDriver, RabbitMQ, SQS a Kafka majú vlastné drivery" />

## Porovnanie

<CompareTable :columns="drivers" :rows="comparison" />

✓ podporované, ✗ nepodporované, ~ podporované s výhradou. Poznámky nižšie vysvetľujú každú bunku.

### Poznámky k Redis `ext-redis`

- **Priorita.** Každá celočíselná priorita je samostatná množina v Redise. `wait()` číta ako prvé najvyššie číslo. Verzia 5.x používala opačné poradie vkladania ([issue #50](https://github.com/tomaj/hermes/issues/50)).
- **Oneskorenie.** Budúce `executeAt` ide cez `ZADD` do zoradenej množiny. Presun používa `ZREM` na práve prečítaný prvok. Vo verzii 5.x volal `SPOP` a nepresunul nikdy ([#95](https://github.com/tomaj/hermes/issues/95)).
- **Perzistencia.** Podľa toho, ako je Redis nastavený (RDB alebo AOF). Hermes to nenastavuje. Hodnoty sú v množinách a jednej zoradenej množine.
- **Ack.** API na potvrdenie neexistuje. `SPOP` prvok zmaže a potom beží handler. Návratová hodnota `true` alebo `false` sa ignoruje.

### Poznámky k Predis

- **Priorita.** Rovnako ako Redis.
- **Oneskorenie.** Budúce `executeAt` ide do zoradenej množiny. Presun používa `ZREM` na práve prečítaný prvok a pošle ho znova len worker, ktorého `ZREM` ho odstránil.
- **Perzistencia.** Rovnako ako Redis.
- **Ack.** Ten istý `SPOP` zo živej množiny.

### Poznámky k RabbitMQ lazy

- **Priorita.** `setupPriorityQueue()` vyhodí `NotSupportedException`. `send()` ignoruje `$priority`.
- **Oneskorenie.** `executeAt` zostane v JSON. `basic_publish` prebehne hneď.
- **Perzistencia.** Fronta je deklarovaná ako durable a správy sú perzistentné (`delivery_mode` 2). Ak chcete ponechať frontu vytvorenú verziou 5.x, odovzdajte `durable: false`.
- **Ack.** Manuálny. `basic_ack` po handleri, `basic_nack` s vrátením do fronty, keď callback zlyhá, prefetch 1. Verzia 5.x používala `no_ack = true` ([#70](https://github.com/tomaj/hermes/issues/70)).

### Poznámky k Amazon SQS

- **Priorita.** Rovnako ako RabbitMQ. Text výnimky uvádza `AmazonSQS`.
- **Oneskorenie.** `executeAt` zostane v JSON. `sendMessage` prebehne hneď.
- **Perzistencia.** SQS uchováva telo, kým ho tento driver nezmaže. Driver maže po návrate callbacku. Neúspešný handler nechá správu znova sa objaviť po uplynutí visibility timeoutu.
- **Ack.** `deleteMessage` beží po úspešnej deserializácii a callbacku. Doručenie je at-least-once. Verzia 5.x mazala pred handlerom ([#69](https://github.com/tomaj/hermes/issues/69)).

### Poznámky k FileSystem

- **Priorita.** Každá priorita je priečinok v adresári. `wait()` číta ako prvé najvyššie číslo.
- **Oneskorenie.** Názov súboru začína časom splatnosti. `wait()` preskakuje súbory, ktoré ešte nie sú splatné.
- **Perzistencia.** Obyčajné súbory na jednom lokálnom súborovom systéme. Nie na NFS.
- **Ack.** Worker premenuje súbor do `processing/` a po callbacku ho zmaže. Správa spadnutého workera sa po visibility timeoute doručí znova.

### Poznámky k Kafka

- **Priorita.** Rovnako ako RabbitMQ. Text výnimky uvádza `Kafka`.
- **Oneskorenie.** `executeAt` zostane v JSON. Správa sa produkuje hneď.
- **Perzistencia.** Kafka drží topic počas svojej retencie, aj po spracovaní správy.
- **Ack.** Offset consumera sa commitne po návrate callbacku. Správa spadnutého workera ide ďalšiemu workerovi skupiny. Správy, ktoré sa nedajú deserializovať, sa commitnú a nahlásia, aby nemohli zablokovať particiu.

V rámci jednej priority v Redise alebo Predise poradie nie je FIFO. Množina nemá poradie a `SPOP` vracia jeden náhodný prvok.

## Ktorý súbor skopírovať

| Máte | Začnite od |
| --- | --- |
| phpredis | [Redis](/sk/guide/drivers/redis), `examples/redis` |
| Predis | [Predis](/sk/guide/drivers/predis), `examples/predis` |
| RabbitMQ | [RabbitMQ](/sk/guide/drivers/rabbitmq), `examples/rabbitmq` |
| AWS SQS | [Amazon SQS](/sk/guide/drivers/sqs), `examples/sqs` |
| Jeden server, bez brokera | [FileSystem](/sk/guide/drivers/filesystem), `examples/filesystem` |
| Kafka | [Kafka](/sk/guide/drivers/kafka), `examples/kafka` |

Pomocníci na ukončenie nie sú drivery. `SharedFileShutdown` funguje s akýmkoľvek driverom. `RedisShutdown` potrebuje ext-redis. `PredisShutdown` potrebuje Predis. Opisuje ich stránka [Ukončenie a limit správ](/sk/guide/shutdown).

## Časté otázky

### Ktorý driver je najbezpečnejšia predvoľba?

`PredisSetDriver`. Nepotrebuje rozšírenie, podporuje priority a presúva odložené správy aj opakovania. Vo verzii 5.x je jediný driver, ktorý to robí.

### Ktorý driver zachová správy, keď worker spadne?

Od 6.0 `LazyRabbitMqDriver`, `AmazonSqsDriver`, `KafkaDriver` a `FileSystemDriver`: správu odstránia až po dokončení handlera. Redis drivery ju vyberú vopred. Vo verzii 5.x to nerobil žiadny. Pozrite [Architektúra](/sk/guide/architecture).

### Môžu emitter a worker používať rôzne drivery?

Nie. Musia používať tú istú triedu drivera s rovnakými kľúčmi, pretože kľúče sú samotná fronta.

### Podporuje niektorý driver potvrdzovanie?

Od 6.0 RabbitMQ potvrdzuje po handleri, SQS a súborový systém mažú po handleri a Kafka commitne po handleri. Redis prvok vyberie vopred.

### Existuje ZeroMQ driver?

Vo verzii 3.0.0 sa presunul z balíka do [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver). Vyžaduje `tomaj/hermes` `^2.1`, takže sa s 6.x nenainštaluje; implementujte `DriverInterface` sami (pozrite [Rozširovanie Hermesu](/sk/guide/extending)).
