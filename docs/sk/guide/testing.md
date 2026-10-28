---
title: "Testovanie"
description: "Testujte handlery Hermesu bez Redisu, spustite testy knižnice, pokrytie a mutačné testovanie."
---

# Testovanie

Testy používajú PHPUnit 13 s konfiguráciou v `phpunit.xml`. Sú v `tests/` pod `Tomaj\Hermes\Test\`. Rizikové testy a varovania beh zhodia.

Test handlera Redis nepotrebuje. Zavolajte `handle()` s `Message` alebo vložte správu do `DummyDriver` a zavolajte `Dispatcher::handle()`. `DummyDriver` je v `tests/` a nie je to trieda, ktorú inštalujete.

<Diagram name="testing-1" alt="Testovanie handlera priamo s novou Message alebo cez DummyDriver a Dispatcher.handle" />

## Príkazy

Skripty v `composer.json` sú `test` (alias `phpunit`), `phpunit`, `phpstan`, `phpcs`, `cs-fix`, `rector`, `rector-fix`, `infection`, `coverage` a `check`, ktorý spustí `phpcs`, `phpstan`, `rector` a `test` v tomto poradí. `phpunit` a `test` bežia bez pokrytia, takže nepotrebujú ani Xdebug, ani PCOV; `coverage` a `infection` potrebujú jedno z nich.

```bash
composer install
composer phpunit
vendor/bin/phpunit
```

Štýl kódu je [PER Coding Style](https://www.php-fig.org/per/coding-style/), ktorý kontroluje PHP-CS-Fixer v režime dry-run nad `src`, `tests` a `examples`. `composer cs-fix` opravy aplikuje:

```bash
composer phpcs
```

PHPStan beží na úrovni `max` s nastaveniami v `phpstan.neon`: bleeding edge, `phpstan-strict-rules`, `phpstan-deprecation-rules` a `phpstan-phpunit`, plus `checkUninitializedProperties` a `reportUnmatchedIgnoredErrors`. Nemá baseline ani ignorovanú chybu, ani v `src/`, ani v `tests/`.

```bash
composer phpstan
```

[Rector](https://getrector.com/) kontroluje `src` a `tests` voči sade PHP 8.4 a sadám dead code, code quality, type declaration a early return v `rector.php`. `composer rector` zmeny len vypíše; `composer rector-fix` ich aplikuje:

```bash
composer rector
```

## Pokrytie

```bash
composer coverage
```

Spustí PHPUnit s `XDEBUG_MODE=coverage`. Reporty:

- HTML: `build/coverage/index.html`
- Clover: `build/logs/clover.xml`

CI spúšťa PHPUnit aj s integračnými testami proti skutočným brokerom na PHP 8.4, PHP 8.4 s `--prefer-lowest`, PHP 8.5 a na nočnom builde PHP 8.6, ktorý môže zlyhať bez zlyhania workflowu. Brána 95 % pokrytia príkazov beží v jobe PHP 8.5, ktorý pokrytie nahráva aj do Coveralls. Ten pri pushi do `master` nahráva aj artefakt `coverage-report`.

Mutačné testovanie používa [Infection](https://infection.github.io/) s `infection.json5`. Zlyhá, keď je MSI pod 85 % alebo MSI pokrytého kódu pod 90 %:

```bash
composer infection
```

Po úspešnom behu CI na `master` workflow dokumentácie zverejní toto HTML na webe, na [ceste s pokrytím](https://tomaj.github.io/hermes/coverage/). Samotná dokumentácia je zvyšok `https://tomaj.github.io/hermes/`. Oba výstupy zdieľajú jeden web GitHub Pages, takže nasadenie pokrytia nemôže prepísať príručku.

## Čo testy nahrádzajú

`Tomaj\Hermes\Test\Driver\DummyDriver` implementuje `DriverInterface` v pamäti. Používajú ho `HandleTest`, `EmitTest` a testy ukončenia, aby nepotrebovali Redis. Nie je súčasťou `src/` a nie je to driver, ktorý inštalujete.

`DummyDriver::setupPriorityQueue()` zoradí kľúče číselne predtým, ako ich `wait()` otočí. Preto `HandleTest::testPriorityProcessing()` vidí vyššiu prioritu ako prvú, čo zodpovedá Redis driverom od 6.0. Vo verzii 5.x Redis drivery nezoraďovali. Správanie Redisu opisuje [issue #50](https://github.com/tomaj/hermes/issues/50).

Testy driverov, ktoré by potrebovali server, mockujú klienta (`Redis`, `Predis\Client`, `SqsClient`, `AbstractConnection` z php-amqplib). Zelený beh PHPUnit nevyžaduje lokálny Redis, RabbitMQ ani účet AWS.

## CI workflowy

| Workflow | Kedy |
| --- | --- |
| CI | push a pull request: PHPUnit s integračnými testami na PHP 8.4, 8.4 lowest, 8.5 a 8.6 (nočný build), štýl kódu, PHPStan level max, Rector, `composer audit` a Infection |
| Examples | end-to-end skripty príkladov proti tomuto checkoutu |
| CodeQL Security Analysis | push a pull request do `master` a raz týždenne |
| OpenSSF Scorecard | push a pull request do `master`, zmeny ochrany vetvy a raz týždenne |
| Deploy Documentation | kontroly diagramov, odkazov a SVG pri pull requestoch a pushoch; nasadenie Pages po úspešnom CI na `master` |

## Časté otázky

### Ako otestovať handler?

Zavolajte `handle()` priamo s `new Message(...)` a overte výsledok a vedľajšie efekty. Redis ani worker netreba. [Návod na uvítací e-mail](/sk/examples/welcome-email#_9-otestujte-handler) obsahuje kompletný PHPUnit test.

### Môžem nainštalovať `DummyDriver`?

Nie. Je v `tests/` tohto repozitára. Prevezmite myšlienku, `DriverInterface` v pamäti, do vlastných testov.

### Potrebujú testy Hermesu Redis alebo RabbitMQ?

Unit testy nie: mockujú klientov, takže `composer phpunit` beží bez akéhokoľvek servera a integračné testy preskočí. `composer test:integration` ich spustí proti brokerom z `compose.yaml` (`docker compose up -d --wait`).

### Existuje skript `composer test`?

Áno. Spustí PHPUnit, rovnako ako `composer phpunit`. `composer check` spustí štýl kódu, PHPStan, Rector a testy.

### Zoraďuje `DummyDriver` priority ako Redis?

Áno, od 6.0. Oba čítajú ako prvé najvyššie číslo priority. Vo verzii 5.x Redis drivery používali opačné poradie registrácie. Pozrite [Priorita](/sk/guide/priority).
