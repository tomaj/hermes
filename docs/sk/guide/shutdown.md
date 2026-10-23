---
title: "Ukončenie a limit správ"
description: "Čisto zastavte workery Hermesu cez súbor alebo kľúč v Redise a recyklujte ich po N správach."
---

# Ukončenie a limit správ

Hermes vie opustiť `Dispatcher::handle()`. Proces však znova spustiť nevie. To robí Supervisor, systemd alebo Kubernetes. Staré názvy `Restart*` boli odstránené vo verzii 4.0.0.

<Diagram name="shutdown-1" alt="Slučka workera kontroluje signál na ukončenie a limit správ, potom skončí a správca procesov ju spustí znova" />

Objekt na ukončenie odovzdajte dispatcheru. Ak má driver `setShutdown()`, konštruktor ho zavolá, takže bežia obe kontroly. Všetkých šesť vstavaných driverov používa `ShutdownTrait`.

```php
$dispatcher = new Dispatcher($driver, $logger, $shutdown);
```

Príklady volajú aj `$driver->setShutdown()` samy. To stačí na vlastnú kontrolu drivera. Kontrola v dispatcheri po každej správe beží len vtedy, keď objekt dostal aj dispatcher.

`ShutdownException` rozširuje `HermesException`. `handle()` ju zachytí, zaloguje `Exiting hermes dispatcher - shutdown` na úrovni `notice` a vráti sa. Akákoľvek iná `Exception`, ktorá unikne z `wait()`, sa zaloguje na úrovni `error` ako `Exiting hermes dispatcher - driver error: <class> - <message>`, odovzdá sa `Debugger::log($exception, Debugger::EXCEPTION)`, keď `Tracy\Debugger::isEnabled()` vráti true, a vyhodí sa znova, takže worker skončí s nenulovým kódom ([#113](https://github.com/tomaj/hermes/issues/113)). Hermes za vás `Debugger::enable()` nevolá.

## SIGTERM a SIGINT

Keď je načítané `ext-pcntl`, `handle()` sa na `SIGTERM` a `SIGINT` čisto zastaví ([#116](https://github.com/tomaj/hermes/issues/116)). Je to vždy zapnuté; nie je čo konfigurovať.

- Aktuálna správa sa dokončí: handler sa vráti, správa sa potvrdí (RabbitMQ) alebo zmaže (SQS) a potom sa vráti `handle()`.
- Nečinný worker sa zastaví pri najbližšej kontrole ukončenia v driveri: hneď pri intervale obnovy `0`, inak do jedného intervalu (predvolene jedna sekunda a na SQS jeden long poll).
- `handle()` zaloguje `Exiting hermes dispatcher - received SIGTERM` (alebo `SIGINT`) na úrovni `notice` a normálne sa vráti, takže proces skončí so stavom 0.
- Signály sa spracúvajú len v týchto bezpečných bodoch, nikdy uprostred kódu vášho handlera. Blokujúce volanie ako `sleep()` v handleri sa po príchode signálu aj tak vráti skôr, ako v každom PHP procese s obsluhou signálu.
- Ďalšie `SIGTERM`/`SIGINT` počas spracovania správy nič nezmenia. `SIGKILL` stále ukončí proces okamžite, čo správcovia procesov posielajú po uplynutí stop timeoutu.
- Signál zastaví len volanie `handle()`, počas ktorého prišiel. Ďalšie volanie `handle()` alebo priame použitie drivera potom beží normálne.
- Obsluhy signálov a nastavenie `pcntl_async_signals()`, ktoré platili pred `handle()`, sa obnovia, keď sa vráti alebo vyhodí výnimku. Konzoly frameworkov (Symfony, Laravel), ktoré si inštalujú vlastné obsluhy signálov, ich po `handle()` dostanú späť.

Bez `ext-pcntl` (napríklad na Windows) sa nič nemení: signál ukončí proces predvolenou akciou. Objekt na ukončenie aj limit správ fungujú s ním aj bez neho. Stop timeout správcu procesov nastavte dlhší ako vaša najpomalšia správa; pozrite [Nasadenie do produkcie](/sk/guide/deployment).

<Diagram name="shutdown-2" alt="Postup: operátor nastaví signál na ukončenie, bežiaci worker dokončí správu a skončí a správca spustí nového" />

## SharedFileShutdown

Súbor môže použiť ktorýkoľvek driver.

```php
use Tomaj\Hermes\Shutdown\SharedFileShutdown;

$shutdown = new SharedFileShutdown('/tmp/hermes_shutdown');
$dispatcher = new Dispatcher($driver, $logger, $shutdown);
```

Zastavenie workerov:

```php
$shutdown->shutdown();
```

`shutdown()` použije predvolene čas `new DateTimeImmutable()`. Zapíše ho s mikrosekundami (`1700000000.123456`) do súboru a čas úpravy súboru nastaví na tú istú sekundu. Rovnako ako `touch` v 5.x prepíše existujúci súbor na mieste, takže súbor si ponechá vlastníka a práva a adresár nemusí byť zapisovateľný. Na rozdiel od `touch` potrebuje právo zápisu do súboru. Workery súbor potrebujú len čítať; worker, ktorý ho prečítať nemôže, použije čas úpravy. `shouldShutdown()` vráti true, keď súbor existuje, čas v ňom nie je v budúcnosti a je neskorší ako čas spustenia workera. Chýbajúci súbor znamená pokračovať. Worker, ktorý sa spustil po ukončení, beží ďalej, aj keď sa spustil v tej istej sekunde.

Funguje aj `touch`. Keď súbor neobsahuje čas alebo bol touchnutý v neskoršej sekunde ako posledné `shutdown()`, použije sa namiesto toho čas úpravy. To je celá sekunda a počíta sa ako začiatok tejto sekundy, takže worker, ktorý sa spustil skôr v tej istej sekunde, beží ďalej.

## RedisShutdown

Pre klienta phpredis `Redis`. Predvolený kľúč je `hermes_shutdown`.

```php
use Tomaj\Hermes\Shutdown\RedisShutdown;

$shutdown = new RedisShutdown($redis);
$dispatcher = new Dispatcher($driver, null, $shutdown);
```

`examples/redis/shutdown.php` je `new RedisShutdown($redis)->shutdown()`.

`shutdown()` nastaví kľúč cez `SET` na čas s mikrosekundami (`1700000000.123456`). `shouldShutdown()` vráti true, len keď platí všetko z tohto:

- `GET` vrátil číslo (chýbajúci kľúč alebo iná hodnota znamená pokračovať)
- čas nie je v budúcnosti (budúca hodnota zatiaľ nikoho nezastaví)
- čas je neskorší ako čas spustenia workera (worker spustený neskôr beží ďalej, aj v tej istej sekunde)

Hodnota v celých sekundách, napríklad zapísaná Hermesom 5.x, sa počíta ako začiatok tejto sekundy.

## PredisShutdown

Rovnaký kľúč a rovnaké tri pravidlá s `Predis\Client`. `GET` chýbajúceho kľúča vráti `null` namiesto `false`. `shutdown()` vráti true, keď je stavový payload Predisu `OK`. Túto triedu používajte s `PredisSetDriver`. `examples/predis/shutdown.php` vytvorí driver a potom zavolá `PredisShutdown::shutdown()`. Vytvorenie drivera na `SET` potrebné nie je. Objekt na ukončenie potrebuje len klienta a kľúč.

```php
use Predis\Client;
use Tomaj\Hermes\Shutdown\PredisShutdown;

$redis = new Client([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
]);

new PredisShutdown($redis)->shutdown();
```

Jeden kľúč zastaví každého workera, ktorý bol spustený pred týmto časom a číta ten istý Redis. Noví workery, ktorých spustí správca procesov, uvidia čas starší ako ich spustenie a bežia ďalej. Ak chcete, aby neskoršie nasadenie použilo nový signál, kľúč zmažte. Ponechať ho je bezpečné pre workery, ktoré sa spustia neskôr. Nie je to bezpečné pre workera, ktorého čas spustenia je stále skorší.

Všetky tri spôsoby ukončenia porovnávajú uložený čas s hodinami hostiteľa workera. Hodiny hostiteľov, ktoré volajú `shutdown()`, a hostiteľov, na ktorých bežia workery, majte synchronizované (NTP). Požiadavka z hostiteľa, ktorého hodiny idú napred, sa považuje za budúcu a workery zastaví, až keď ich hodiny tento čas dosiahnu. Požiadavka z hostiteľa, ktorého hodiny meškajú, nezastaví workery spustené v rámci tohto rozdielu pred požiadavkou. Hermes 5.x mal na hodinách rovnakú závislosť.

## Limit správ

`MaxItemsTrait` majú všetkých šesť driverov.

```php
$driver->setMaxProcessItems(1000);
```

Predvolená hodnota `0` znamená, že `shouldProcessNext()` vždy vráti true. Keď `processed` dosiahne limit, `wait()` sa vráti pri najbližšej kontrole v slučke. Redis, Predis a SQS urobia `break`. RabbitMQ preruší obe slučky a potom zatvorí kanál aj pripojenie. Počítadlo sa zvyšuje po návrate callbacku, takže správa, ktorá limit dosiahne, sa ešte spracuje.

Proces potom opustí `handle()` bez `ShutdownException`. Správca procesov s `autorestart` alebo `Restart=always` spustí nový. To je vzor na obmedzenie pamäte: recyklovať proces každých N správ namiesto toho, aby jeden PHP proces bežal navždy.

`processed()` vracia aktuálny počet. `incrementProcessedItems()` je verejná a volajú ju drivery. Sami ju volať nemusíte.

## Časté otázky

### Ako zastaviť workery po nasadení?

Zavolajte `shutdown()` na tom istom `RedisShutdown`, `PredisShutdown` alebo `SharedFileShutdown`, ktorý používajú workery. Každý worker dokončí aktuálnu správu a vráti sa z `handle()`.

### Zastavia sa aj noví workery?

Nie. Workery spustené po čase ukončenia bežia ďalej, pretože signál sa porovnáva s časom spustenia každého workera.

### Reštartuje Hermes proces?

Nie. Na spustenie nového procesu po skončení starého použite Supervisor, systemd alebo Kubernetes. Pozrite [Nasadenie do produkcie](/sk/guide/deployment).

### Čo robí `setMaxProcessItems()`?

Spôsobí, že sa `wait()` vráti po N spracovaných správach, takže proces čisto skončí a správca procesov spustí nový. `0` znamená bez limitu.

### Spracúva Hermes SIGTERM?

Áno, keď je načítané `ext-pcntl`: worker dokončí aktuálnu správu a vráti sa (pozrite [SIGTERM a SIGINT](#sigterm-a-sigint)). Správca procesov po stop timeoute aj tak pošle `SIGKILL`, preto tento timeout nastavte dlhší ako jedna správa.
