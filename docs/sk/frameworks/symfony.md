---
title: "Hermes v Symfony"
description: "Používajte Hermes v Symfony 7.4 bez Messengera: autowirované služby v services.yaml, konzolový worker, logovanie cez Monolog, hermes:restart a testy so zamockovaným EmitterInterface."
howto:
  totalTime: PT20M
  tools: [PHP 8.4, Composer, Symfony 7.4, Redis alebo RabbitMQ alebo SQS]
---

# Symfony

Táto stránka pridá Hermes do nového projektu v Symfony 7.4, vedľa Messengera alebo namiesto neho. Controller vloží `user.registered` a `bin/console hermes:work` ju spracuje. Najprv si prečítajte [Integráciu do frameworkov](/sk/frameworks/), kde sú časti spoločné pre všetky frameworky.

<ProjectSource name="frameworks/symfony" :files="['config/services.yaml', 'src/Command/HermesWorkCommand.php', 'src/Controller/UserController.php', 'tests/Controller/UserControllerTest.php']" />

## 1. Inštalácia

```bash
composer create-project symfony/skeleton:"7.4.*" app
cd app
composer require tomaj/hermes:dev-master symfony/monolog-bundle
composer require predis/predis              # only for the predis driver
composer require php-amqplib/php-amqplib    # only for the rabbitmq driver
composer require aws/aws-sdk-php            # only for the sqs driver
```

Kým Hermes 6.0 nemá tag, vyžadujte `tomaj/hermes:dev-master` ako vyššie; po vydaní prejdite na `^6.0`. Bez `monolog-bundle` je autowirovaný `LoggerInterface` minimálny logger Symfony, ktorý zapisuje na stderr.

## 2. Konfigurácia služieb

Celý `config/services.yaml` je nižšie. Parameter `hermes` zbiera premenné prostredia, `DriverFactory` z neho vytvorí driver a obe rozhrania sú aliasy tried Hermesu. Handler sa registruje položkou `calls` na dispatcheri.

<<< @/../examples/frameworks/symfony/config/services.yaml{yaml} [config/services.yaml]

V `.env.local` nastavte `HERMES_DRIVER` na `redis`, `predis`, `rabbitmq` alebo `sqs` a každej aplikácii dajte vlastnú `HERMES_QUEUE`.

<<< @/../examples/frameworks/symfony/src/Hermes/DriverFactory.php{php} [src/Hermes/DriverFactory.php]

Monolog sa autowiruje ako `LoggerInterface`, takže riadky Hermesu skončia vo `var/log/dev.log` (alebo vo vašich produkčných handleroch). Pozrite [Logovanie](/sk/guide/logging).

## 3. Vloženie správy z controllera

<<< @/../examples/frameworks/symfony/src/Controller/UserController.php{php} [src/Controller/UserController.php]

Príkaz producenta, ktorým to nižšie vyskúšate:

<<< @/../examples/frameworks/symfony/src/Command/RegisterUserCommand.php{php} [src/Command/RegisterUserCommand.php]

## 4. Napíšte handler

<<< @/../examples/frameworks/symfony/src/Hermes/UserRegisteredHandler.php{php} [src/Hermes/UserRegisteredHandler.php]

Je to autowirovaná služba, takže jej konštruktor si môže vypýtať čokoľvek z kontajnera. Výnimkou spustíte opakovanie, vrátením `false` to vzdáte; pozrite [Opakovanie](/sk/guide/retry).

## 5. Spustite workera

<<< @/../examples/frameworks/symfony/src/Command/HermesWorkCommand.php{php} [src/Command/HermesWorkCommand.php]

```bash
bin/console hermes:work                          # terminal 1
bin/console app:register-user jane@example.com   # terminal 2
grep 'Welcome email sent' var/log/dev.log
```

`--priority=200` obmedzí workera na jednu prioritnú frontu; pozrite [Priorita](/sk/guide/priority).

## 6. Reštart workerov pri nasadení

<<< @/../examples/frameworks/symfony/src/Command/HermesRestartCommand.php{php} [src/Command/HermesRestartCommand.php]

Po `cache:clear` spustite `bin/console hermes:restart`. Workery sa zastavia po aktuálnej správe a Supervisor spustí nové:

<<< @/../examples/frameworks/symfony/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

systemd opisuje [Nasadenie do produkcie](/sk/guide/deployment).

## 7. Otestujte producenta

V testovacom kontajneri nahraďte `EmitterInterface` mockom. Od Hermesu 6.0 je `Emitter` `final` a nedá sa zamockovať.

<<< @/../examples/frameworks/symfony/tests/Controller/UserControllerTest.php{php} [tests/Controller/UserControllerTest.php]

## Náhodná zložka opakovania a služba `Random\Randomizer`

Od Hermesu 6.0 `Dispatcher` prijíma voliteľný `?Random\Randomizer $randomizer` (pozrite [Integrácia do frameworkov](/sk/frameworks/#nahodna-zlozka-opakovania-a-zaregistrovany-random-randomizer)). Ak vaša aplikácia registruje službu `Random\Randomizer`, autowiring ju odovzdá dispatcheru a náhodná zložka opakovania pochádza z tohto enginu. Ak chcete zachovať predvolený bezpečný engine, na Hermese 6.0 alebo novšom pridajte toto do služby dispatchera:

```yaml
    Tomaj\Hermes\DispatcherInterface:
        class: Tomaj\Hermes\Dispatcher
        arguments:
            $randomizer: null
```

Na Hermese 5.x kontajner zlyhá s `has no argument named "$randomizer"`, preto to tam vynechajte.

## Celý zdrojový kód

Všetky súbory, s `install.sh`, `e2e.sh` a programom pre Supervisor, sú v [`examples/frameworks/symfony` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/frameworks/symfony). Overené so Symfony 7.4.21 a monolog-bundle 4.1.0 na PHP 8.4, so všetkými drivermi.

## Časté otázky

### Potrebujem Symfony Messenger?

Nie. Toto nastavenie Messenger neinštaluje ani nepoužíva. Oba môžu bežať v jednej aplikácii, ak používajú rôzne fronty; pozrite [Integrácia do frameworkov](/sk/frameworks/#caste-otazky).

### Prečo je handler zaregistrovaný v `services.yaml` a nie tagom?

Jeden riadok `calls` na typ správy sa ľahko číta a nepotrebuje compiler pass. Pri veľa handleroch funguje aj tagovaný iterátor a malý cyklus vo factory. Pozrite [Pojmy](/sk/guide/concepts).

### Worker po nasadení nevidí môj nový kód. Prečo?

Je to dlhobežiaci proces so starým kontajnerom v pamäti. Spustite `bin/console hermes:restart`. Pozrite [Ukončenie a limit správ](/sk/guide/shutdown).

### Môže worker bežať s `APP_ENV=prod`?

Áno, a mal by. Pred spustením zahrejte cache, aby prvý worker nevytváral kontajner, kým ostatné čakajú. Pozrite [Nasadenie do produkcie](/sk/guide/deployment).

### Ako nasmerovať driver sqs na LocalStack alebo moto?

Nastavte `HERMES_SQS_ENDPOINT=http://127.0.0.1:4566` a fiktívne kľúče AWS. Pozrite [Amazon SQS](/sk/guide/drivers/sqs).
