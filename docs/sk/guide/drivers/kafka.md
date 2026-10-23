---
title: "Driver Kafka"
description: "KafkaDriver pre Hermes: Apache Kafka cez ext-rdkafka. Potvrdené odoslania, consumer groups, commit po handleri, doručenie at-least-once."
---

# Kafka

`Tomaj\Hermes\Driver\KafkaDriver` posiela správy do topicu [Apache Kafka](https://kafka.apache.org/) a konzumuje ich v consumer group. Používa [`ext-rdkafka`](https://github.com/arnaud-lb/php-rdkafka) 6, PHP väzbu na librdkafka. Testuje sa proti Kafka 4.3 v režime KRaft.

```php
public function __construct(
    RdKafka\Producer $producer,
    ?RdKafka\KafkaConsumer $consumer = null,
    string $topic = 'hermes',
    int $consumeTimeout = 1000,
    int $flushTimeout = 10000
)
```

- `$producer` posiela správy. Worker ho potrebuje tiež, pretože dispatcher posiela opakovania späť do topicu.
- `$consumer` prijíma správy vo `wait()`. Emitter ho môže vynechať. Bez neho `wait()` vyhodí `KafkaDriverException`.
- `$consumeTimeout` je, koľko milisekúnd `wait()` čaká na ďalšiu správu, kým znova skontroluje [ukončenie](/sk/guide/shutdown).
- `$flushTimeout` je, koľko milisekúnd `send()` čaká, kým Kafka správu potvrdí.

Prázdny topic alebo timeout pod 1 vyhodí `InvalidArgumentException`.

## Inštalácia

```bash
pecl install rdkafka          # potrebuje librdkafka, napr. apt install librdkafka-dev
```

`ext-rdkafka` je `suggest` balíka `tomaj/hermes`, preto ho pridajte do vlastného `composer.json` (`"ext-rdkafka": "^6.0"`). Čisté PHP Kafka klienty už nie sú udržiavané, preto driver používa rozšírenie. Pozrite [Časté otázky](#preco-ext-rdkafka-a-nie-cisty-php-klient).

## Vloženie a spracovanie

Toto je `examples/kafka`. Potvrdzuje objednávky a `make up` spustí ten istý Kafka obraz ako testy Hermesu.

<ProjectSource name="kafka" :files="['src/Hermes.php', 'src/OrderHandler.php', 'bin/emit.php', 'bin/worker.php', 'bin/shutdown.php', 'docker-compose.yml', 'e2e.sh']" />

Jedna trieda skladá drivery pre obe strany:

<<< @/../examples/kafka/src/Hermes.php{php} [src/Hermes.php]

Consumer **musí** mať `enable.auto.commit=false`. Inak librdkafka potvrdí správy skôr, než handler skončí, a pád ich stratí. Potrebuje aj `group.id`.

<<< @/../examples/kafka/bin/emit.php{php} [bin/emit.php]

<<< @/../examples/kafka/bin/worker.php{php} [bin/worker.php]

Worker používa [`SharedFileShutdown`](/sk/guide/shutdown), aby `make e2e` mohol čisto zastaviť workera pred spustením druhého workera tej istej skupiny:

<<< @/../examples/kafka/bin/shutdown.php{php} [bin/shutdown.php]

## Odoslanie

<Diagram name="drivers-kafka-1" alt="Emitter produkuje každú správu do topicu s kľúčom podľa id správy a čaká na potvrdenie. Každú particiu topicu konzumuje jeden worker consumer group." />

`send()` produkuje JSON do topicu s kľúčom podľa id správy a potom volá `flush($flushTimeout)`. Vráti sa až potom, čo Kafka správu potvrdila. Ak Kafka včas nepotvrdí, vyhodí `KafkaDriverException` s kódom chyby librdkafka. Odoslanie stojí jedno round-trip k brokeru, čo je v poriadku pre webovú požiadavku a trochu pomalé pre hromadné importy.

`flush()` nahlási brokera, ktorý včas neodpovie. Správa, ktorú broker natrvalo odmietne, napríklad na topic, do ktorého klient nesmie písať, tiež opustí frontu a chybu ukáže len delivery report librdkafka. Aby ste na týchto prípadoch hlasno zlyhali, nastavte na `Conf` producera callback delivery reportu cez `setDrMsgCb()` a pri `$message->err` inom než `0` vyhoďte výnimku alebo zalogujte.

Kľúč rozhoduje o particii. Id správ sú náhodné, takže správy sa rozložia po všetkých particiach. Kafka drží poradie len v rámci particie, takže pri viac než jednej particii správy Hermesu nie sú FIFO.

## Konzumácia a commit

<Diagram name="drivers-kafka-2" alt="wait() skonzumuje správu, deserializuje ju, spustí handler a commitne offset. Správa, ktorú nemožno deserializovať, sa commitne a výnimka sa vyhodí znova. Keď callback vyhodí výnimku, offset ostane necommitnutý a worker sa zastaví." />

`wait()` raz prihlási consumer na topic a potom beží v slučke. Každá slučka skontroluje ukončenie a limit správ a potom zavolá `consume($consumeTimeout)`:

- **Správa.** Driver ju deserializuje, spustí callback a potom synchronne commitne offset. Doručenie je at-least-once: worker, ktorý zomrie pred commitom, nechá správu ďalšiemu workerovi skupiny.
- **Callback vyhodí výnimku.** Offset sa necommitne a výnimka zastaví workera. Ďalší worker skupiny začne znova pri tej správe. Chyby handlerov a [opakovania](/sk/guide/retry) rieši dispatcher skôr, než dôjdu k driveru.
- **Neplatný JSON alebo prázdna hodnota.** Driver ju commitne a vyhodí `SerializeException`. Správa, ktorá zlyhá pri každom doručení, by inak natrvalo zablokovala svoju particiu.
- **Timeout alebo koniec particie.** Driver skontroluje ukončenie a konzumuje znova.
- **Akákoľvek iná chyba consumera.** Driver vyhodí `KafkaDriverException` so správou a kódom librdkafka.

Workery s tým istým `group.id` zdieľajú particie. Topic s tromi particiami udrží zaneprázdnených až troch workerov. Viac workerov než particií čaká nečinne.

## Obmedzenia

- **Bez priorít.** `setupPriorityQueue()` vyhodí `NotSupportedException`, rovnako ako RabbitMQ a SQS. `send()` ignoruje `$priority` a `wait()` ignoruje `$priorities`. Ak potrebujete pruhy, použite jeden topic a driver na prioritu. Pozrite [Priorita](/sk/guide/priority).
- **Bez oneskorenia.** Správa s budúcim `executeAt` sa doručí hneď, rovnako ako opakovania. Zlyhávajúci handler sa opakuje, kým nedosiahne `maxRetry()`. Pozrite [Odložené spracovanie](/sk/guide/delayed).
- **Commit na správu.** Každá spracovaná správa stojí synchronný commit. Pri veľmi vysokom throughput-e dávkujte mimo Hermesu.
- **Nastavenie topicu.** Driver topicy nevytvára. Kafka jeden vytvorí pri prvom použití, keď je zapnuté `auto.create.topics.enable`. V produkcii si topic vytvorte sami s particiami a retenciou, ktoré potrebujete.

## Časté otázky

### Prečo ext-rdkafka a nie čistý PHP klient?

Čisté PHP klienty [`longlang/phpkafka`](https://github.com/swoole/phpkafka) a [`nmred/kafka-php`](https://github.com/weiboad/kafka-php) nemali release od roku 2023 a 2017. `ext-rdkafka` sa udržiava a obaľuje librdkafka, klienta, na ktorom stoja väčšina Kafka nástrojov. Je to `suggest`, takže ho inštalujete, len keď tento driver používate.

### Môžu viacerí workeri konzumovať ten istý topic?

Áno. Dajte im rovnaké `group.id` a Kafka pridelí každú particiu jednému z nich. Pozrite [Konzumácia a commit](#konzumacia-a-commit).

### Čo sa stane, keď worker spadne?

Offset správy, ktorú spracúval, nie je commitnutý, takže ju dostane ďalší worker skupiny. Robte handlery idempotentné. Pozrite [Architektúra](/sk/guide/architecture).

### Sú podporované odložené správy a priority?

Nie. Kafka doručuje v poradí a hneď. Porovnajte drivery na stránke [Drivery](/sk/guide/drivers/).

### Ktoré verzie Kafka sú podporované?

Integračné testy bežia proti Kafka 4.3.1 (`apache/kafka`, KRaft) s ext-rdkafka 6.0.5 a librdkafka 2. librdkafka vie hovoriť aj so staršími brokermi.

### Existuje úplný príklad?

Áno. [`examples/kafka`](https://github.com/tomaj/hermes/tree/master/examples/kafka) má `docker-compose.yml` pre Kafka a jeho `make e2e` overí, že objednávky sa spracujú raz, že `SharedFileShutdown` workera zastaví a že nový worker skupiny pokračuje po poslednom commit-e.
