---
title: "Integrácia do frameworkov"
description: "Zapojte Hermes do Nette, Laravelu alebo Symfony: jeden driver z konfigurácie, EmitterInterface pre producentov, konzolový worker hermes:work, logovanie, ukončenie a testy."
---

# Integrácia do frameworkov

Hermes nemá bundle pre framework a ani ho nepotrebuje. Jeho triedy dostávajú závislosti v konštruktore, takže ich vytvorí akýkoľvek kontajner. Stránky v tejto sekcii ukazujú tú istú malú integráciu v troch frameworkoch:

- [Nette 3.2](/sk/frameworks/nette): služby v súbore NEON, Tracy ako PSR-3 logger, [contributte/console](https://github.com/contributte/console) pre príkaz workera.
- [Laravel 11 a 12](/sk/frameworks/laravel): service provider, `config/hermes.php` a príkazy Artisan.
- [Symfony 7.4](/sk/frameworks/symfony): `services.yaml` s autowiringom, Monolog a príkazy Symfony Console.

<Diagram name="frameworks-1" alt="DriverFactory vytvorí jeden driver z konfigurácie. Kontajner dá EmitterInterface controllerom a príkazom, ktoré posielajú správy do Redisu, RabbitMQ alebo SQS. Príkaz hermes:work dostane DispatcherInterface, čaká na správy a spúšťa zaregistrované handlery." />

Každý príklad je súbor súborov v [`examples/frameworks`](https://github.com/tomaj/hermes/tree/master/examples/frameworks) a kód na týchto stránkach sa načítava z nich. Každý bol nainštalovaný do novo vytvoreného projektu a spustený proti Redisu (ext-redis aj Predis), RabbitMQ a SQS (lokálny server moto).

## Čo má každá integrácia

| Časť | Čo to je | Kto ju používa |
|---|---|---|
| `DriverFactory` | Vytvorí `DriverInterface` z jednoduchej konfigurácie: `redis` (ext-redis), `predis`, `rabbitmq` alebo `sqs`, plus názov fronty a limit správ. | kontajner |
| `EmitterInterface` | Zdieľaný `Emitter` s PSR-3 loggerom frameworku. | controllery, príkazy, joby |
| `DispatcherInterface` | `Dispatcher` s loggerom, ukončením a handlermi zaregistrovanými cez `registerHandler()`. | len príkaz workera |
| `ShutdownInterface` | `SharedFileShutdown` nad súborom v dočasnom priečinku alebo v storage aplikácie. | worker a `hermes:restart` |
| `hermes:work` | Volá `$dispatcher->handle()`. Blokuje až do ukončenia alebo limitu správ. `--priority` ho obmedzí na niektoré priority. | Supervisor alebo systemd |
| `hermes:restart` | Volá `$shutdown->shutdown()`. Bežiace workery sa zastavia po aktuálnej správe. | skript nasadenia |
| `app:register-user` | Ukážkový producent, ktorý vkladá `user.registered`. | vy, na vyskúšanie |

Tá istá `DriverFactory` a `UserRegisteredHandler` sa používajú vo všetkých troch frameworkoch:

<<< @/../examples/frameworks/symfony/src/Hermes/DriverFactory.php{php} [src/Hermes/DriverFactory.php]

Driver sa vytvorí raz na proces. Webový proces aj worker musia dostať rovnakú triedu drivera a názov fronty; factory ich číta z jednej konfigurácie, takže sa nerozídu. Čo ktorý driver dokáže, nájdete v [Prehľade driverov](/sk/guide/drivers/overview).

<<< @/../examples/frameworks/symfony/src/Hermes/UserRegisteredHandler.php{php} [src/Hermes/UserRegisteredHandler.php]

## Pravidlá platné v každom frameworku

- **Producenti závisia od `EmitterInterface`, nikdy od `Emitter`.** Od Hermesu 6.0 sú triedy `final`, takže PHPUnit nevie `Emitter` zamockovať. Mockujte rozhranie; každá stránka frameworku má test.
- **Dispatcher si vytvára len worker.** Vytvoriť ho vo webovej požiadavke neuškodí, ale nikdy tam nie je potrebný.
- **Každá aplikácia nech má vlastný názov fronty.** Dve aplikácie, ktoré zdieľajú jeden Redis a predvolený kľúč `hermes`, si navzájom berú správy. Worker zahodí správu, pre ktorú nemá handler.
- **Po nasadení reštartujte workery.** Worker drží starý kód, kým neskončí. Ako posledný krok nasadenia spustite `hermes:restart` a nové workery nechajte spustiť správcu procesov. Pozrite [Ukončenie a limit správ](/sk/guide/shutdown) a [Nasadenie do produkcie](/sk/guide/deployment).
- **Zastavenie signálom potrebuje `ext-pcntl`.** S ním `SIGTERM` od Supervisora, systemd alebo Kubernetes nechá workera dokončiť správu a skončiť, aj vo vnútri `hermes:work` (Hermes počas behu `handle()` preberá obsluhy signálov konzoly). Bez neho signál ukončí PHP okamžite, aj uprostred handlera, preto najprv zavolajte `hermes:restart`. Pozrite [Ukončenie a limit správ](/sk/guide/shutdown#sigterm-a-sigint).

## Náhodná zložka opakovania a zaregistrovaný `Random\Randomizer`

Hermes 6.0 ([#103](https://github.com/tomaj/hermes/pull/103)) pridáva do `Dispatcher`a voliteľný štvrtý argument konštruktora: `?Random\Randomizer $randomizer = null`. Bez neho náhodná zložka opakovania používa nový `Randomizer` s bezpečným enginom. Pozrite [Opakovanie](/sk/guide/retry).

Kontajnery, ktoré dispatcher autowirujú, vložia službu `Random\Randomizer`, ak ju aplikácia zaregistrovala na niečo iné, napríklad `Mt19937` so seedom pre fixtures. Časy opakovania potom pochádzajú z tohto enginu. Každá stránka frameworku ukazuje, ako namiesto toho odovzdať `null`. Tento riadok pridajte len pri Hermese 6.0 alebo novšom: vo verzii 5.x a vo vydaniach pred #103 argument neexistuje a kontajner konfiguráciu odmietne.

Provider pre Laravel vytvára dispatcher cez `new Dispatcher(...)`, takže sa ho to netýka.

## Overené s

| Framework | Verzie | Drivery | Ďalej overené |
|---|---|---|---|
| Nette | nette/web-project, nette/application 3.3.0, nette/di 3.2.6, contributte/console 0.11.0 | ext-redis, Predis 3.7, RabbitMQ (php-amqplib 3.7.5), SQS na moto | POST na presenter, PHPUnit test, Supervisor |
| Laravel | 11.57.0 a 12.70.0 | tie isté štyri | feature testy, Supervisor |
| Symfony | 7.4.21, monolog-bundle 4.1.0 | tie isté štyri | POST na controller, test WebTestCase, Supervisor |

PHP 8.4, Hermes `dev-master`. Každý framework bol spustený aj na Hermese 5.0.0 a na vetvách [#103](https://github.com/tomaj/hermes/pull/103) a [#105](https://github.com/tomaj/hermes/pull/105).

## Časté otázky

### Existuje bundle alebo balík Hermesu pre môj framework?

Nie. Integrácia je factory, niekoľko definícií služieb a dva konzolové príkazy, ktoré ukazujú stránky [Nette](/sk/frameworks/nette), [Laravel](/sk/frameworks/laravel) a [Symfony](/sk/frameworks/symfony). Skopírujte ich a zmeňte, čo potrebujete.

### Môžem Hermes používať vedľa Laravel queues alebo Symfony Messenger?

Áno. Hermes sa ich nedotýka. Pre Hermes použite iný kľúč v Redise alebo inú frontu v RabbitMQ, aby si navzájom nečítali správy.

### Ktorý driver nakonfigurovať?

Predis alebo ext-redis, ak chcete priority a odložené správy, RabbitMQ alebo SQS, ak ich už prevádzkujete. Čo ktorý driver podporuje, uvádza [porovnanie](/sk/guide/drivers/).

### Potrebujem jedného workera na handler?

Nie. Jeden proces `hermes:work` spúšťa všetky handlery zaregistrované v dispatcheri. Pre vyššiu priepustnosť spustite viac procesov a cez `--priority` si niektoré nechajte na urgentné správy. Pozrite [Priorita](/sk/guide/priority).

### Ako testovať kód, ktorý vkladá správy?

Typujte `EmitterInterface` a v teste ho nahraďte PHPUnit mockom. Každá stránka frameworku má funkčný test a handlery pokrýva [Testovanie](/sk/guide/testing).
