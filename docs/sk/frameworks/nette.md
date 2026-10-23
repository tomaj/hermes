---
title: "Hermes v Nette"
description: "Používajte Hermes v Nette 3.2: služby v súbore NEON, Tracy ako PSR-3 logger, príkaz workera cez contributte/console, hermes:restart a testy so zamockovaným EmitterInterface."
howto:
  totalTime: PT20M
  tools: [PHP 8.4, Composer, Nette 3.2, Redis alebo RabbitMQ alebo SQS]
---

# Nette

Táto stránka pridá Hermes do nového `nette/web-project`. Presenter vloží `user.registered` a `php bin/console hermes:work` ju spracuje. Najprv si prečítajte [Integráciu do frameworkov](/sk/frameworks/), kde sú časti spoločné pre všetky frameworky.

<ProjectSource name="frameworks/nette" :files="['config/hermes.neon', 'app/Bootstrap.php', 'app/Console/HermesWorkCommand.php', 'app/Presentation/User/UserPresenter.php']" />

## 1. Inštalácia

```bash
composer create-project nette/web-project app
cd app
composer require tomaj/hermes:dev-master contributte/console
composer require predis/predis              # only for the predis driver
composer require php-amqplib/php-amqplib    # only for the rabbitmq driver
composer require aws/aws-sdk-php            # only for the sqs driver
```

Kým Hermes 6.0 nemá tag, vyžadujte `tomaj/hermes:dev-master` ako vyššie; po vydaní prejdite na `^6.0`. Nette nemá vlastnú konzolu; [contributte/console](https://github.com/contributte/console) pridá do DI kontajnera príkazy Symfony Console.

## 2. Konfigurácia služieb

Celý Hermes je v jednom súbore NEON. Driver a názov fronty pre každé prostredie dajte do `config/local.neon` a každej aplikácii dajte vlastnú `queue`.

<<< @/../examples/frameworks/nette/config/hermes.neon{yaml} [config/hermes.neon]

`DriverFactory` premení parameter `hermes` na driver:

<<< @/../examples/frameworks/nette/app/Hermes/DriverFactory.php{php} [app/Hermes/DriverFactory.php]

`Bootstrap` načíta `hermes.neon` a dostane `bootConsoleApplication()` pre `bin/console`:

<<< @/../examples/frameworks/nette/app/Bootstrap.php{php} [app/Bootstrap.php]

<<< @/../examples/frameworks/nette/bin/console{php} [bin/console]

## 3. Logovanie cez Tracy

`TracyToPsrLoggerAdapter` dá Hermesu logger Tracy ako `LoggerInterface`. Riadky `Start handle message` idú do `log/info.log`. Tracy nemá úroveň notice, takže oznámenie `Exiting hermes dispatcher - shutdown` skončí v `log/warning.log`. Ak chcete PSR-3 úrovne bez zmeny, zaregistrujte namiesto toho ako `LoggerInterface` logger Monolog. Pozrite [Logovanie](/sk/guide/logging).

## 4. Vloženie správy z presentera

<<< @/../examples/frameworks/nette/app/Presentation/User/UserPresenter.php{php} [app/Presentation/User/UserPresenter.php]

`POST /user/create` s `email` vráti `201`, chýbajúci e-mail `422` a `GET` `405`. Príkaz producenta, ktorý sa používa nižšie:

<<< @/../examples/frameworks/nette/app/Console/RegisterUserCommand.php{php} [app/Console/RegisterUserCommand.php]

## 5. Napíšte handler

<<< @/../examples/frameworks/nette/app/Hermes/UserRegisteredHandler.php{php} [app/Hermes/UserRegisteredHandler.php]

Výnimkou spustíte opakovanie, vrátením `false` to vzdáte; pozrite [Opakovanie](/sk/guide/retry).

## 6. Spustite workera

<<< @/../examples/frameworks/nette/app/Console/HermesWorkCommand.php{php} [app/Console/HermesWorkCommand.php]

```bash
php bin/console hermes:work                          # terminal 1
php bin/console app:register-user jane@example.com   # terminal 2
grep 'Welcome email sent' log/info.log
```

Ak sa po zmene súboru NEON kontajner neprestaví, zmažte `temp/cache` (v debug režime sa prestavuje sám).

## 7. Reštart workerov pri nasadení

<<< @/../examples/frameworks/nette/app/Console/HermesRestartCommand.php{php} [app/Console/HermesRestartCommand.php]

Ako posledný krok nasadenia, po vymazaní cache, spustite `php bin/console hermes:restart`. Workery sa zastavia po aktuálnej správe a Supervisor spustí nové:

<<< @/../examples/frameworks/nette/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

Súbor na ukončenie je `temp/hermes-shutdown`. Ak nasadenie nahrádza priečinok `temp`, workery zo starého vydania nový súbor nikdy neuvidia; použite cestu mimo vydania, napríklad zdieľaný priečinok. Pozrite [Ukončenie a limit správ](/sk/guide/shutdown) a [Nasadenie do produkcie](/sk/guide/deployment).

## 8. Otestujte producenta

Príkazu odovzdajte mock `EmitterInterface`. Od Hermesu 6.0 je `Emitter` `final` a nedá sa zamockovať.

<<< @/../examples/frameworks/nette/tests/RegisterUserCommandTest.php{php} [tests/RegisterUserCommandTest.php]

## Náhodná zložka opakovania a služba `Random\Randomizer`

Od Hermesu 6.0 `Dispatcher` prijíma voliteľný `?Random\Randomizer $randomizer` (pozrite [Integrácia do frameworkov](/sk/frameworks/#nahodna-zlozka-opakovania-a-zaregistrovany-random-randomizer)). Nette ho autowiruje: ak má kontajner službu `Random\Randomizer`, dispatcher ju dostane. Ak chcete zachovať predvolený bezpečný engine, na Hermese 6.0 alebo novšom zapíšte dispatcher takto:

```yaml
	hermes.dispatcher:
		create: Tomaj\Hermes\Dispatcher(randomizer: null)
```

Na Hermese 5.x Nette zlyhá s `Unable to pass specified arguments to Tomaj\Hermes\Dispatcher::__construct()`, preto to tam vynechajte.

## Celý zdrojový kód

Všetky súbory, s `install.sh`, `e2e.sh` a programom pre Supervisor, sú v [`examples/frameworks/nette` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/frameworks/nette). Overené s nette/application 3.3.0, nette/di 3.2.6, contributte/console 0.11.0 a Tracy 2.12 na PHP 8.4, so všetkými drivermi.

## Časté otázky

### Prečo contributte/console a nie obyčajný PHP skript?

Príkazy dostávajú závislosti z kontajnera ako presentery a `--priority` aj nápovedu máte zadarmo. Funguje aj obyčajný `bin/worker.php`, ktorý vytvorí kontajner a zavolá `handle()`; pozrite [návod na uvítací e-mail](/sk/examples/welcome-email).

### Môžem namiesto Tracy použiť Monolog?

Áno. Zaregistrujte službu `Monolog\Logger` a odstráňte riadok s `TracyToPsrLoggerAdapter`, aby sa autowiroval len jeden `LoggerInterface`. Pozrite [Logovanie](/sk/guide/logging).

### Kam dať driver pre jednotlivé prostredia?

Do `config/local.neon`, ktorý `Bootstrap` načíta ako posledný. Napríklad `parameters: hermes: driver: rabbitmq`. Ako si vybrať, nájdete v [Prehľade driverov](/sk/guide/drivers/overview).

### Čaká presenter na handler?

Nie. `emit()` len zapíše správu do fronty a vráti sa; worker ju spracuje neskôr. Pozrite [Pojmy](/sk/guide/concepts).

### Ako spustiť viac workerov?

Zvýšte `numprocs` v programe pre Supervisor. Každý worker berie z tej istej fronty po jednej správe. Pozrite [Nasadenie do produkcie](/sk/guide/deployment).
