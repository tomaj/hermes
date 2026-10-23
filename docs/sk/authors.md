---
title: "Autori a prispievatelia"
description: "Kto vytvoril a spravuje Hermes, ľudia, ktorí doň prispeli, a história vydaní od roku 2015."
---

# Autori a prispievatelia

<AuthorsPage />

Údaje vyššie pochádzajú z verejného profilu na GitHube a zoznam z GitHub contributors API pre `tomaj/hermes`. `node scripts/update-authors.mjs` v `docs/` ich obnoví.

## História projektu

Prvý commit je z 12. októbra 2015. Dátumy sú tagy vydaní.

| Verzia | Dátum | Čo sa zmenilo |
| --- | --- | --- |
| 0.1.0 | 2015-10-28 | Prvé vydanie s tagom |
| 1.0.0 | 2016-09-02 | Prvá stabilná verzia. Potvrdzovanie v RabbitMQ driveri |
| 2.0.0 | 2018-10-17 | Naplánované správy (`executeAt`), typové deklarácie, `Emitter` nahrádza `Dispatcher::emit()` |
| 3.0.0 | 2020-10-13 | Plynulé ukončenie cez Redis. ZeroMQ driver sa presunul do [vlastného balíka](https://github.com/tomaj/hermes-zmq-driver) |
| 4.0.0 | 2021-02-02 | `Restart` premenovaný na `Shutdown`, `PredisSetDriver` oddelený od `RedisSetDriver`, len `LazyRabbitMqDriver` |
| 4.2.0 | 2024-04-29 | `unregisterHandler()` a `unregisterAllHandlers()` |
| 5.0.0 | 2025-11-19 | Posledné vydanie; jeho dokumentácia je [archív 5.x](/sk/5.x/) |

Celý zoznam je v [Zozname zmien](/sk/changelog) a vo [vydaniach na GitHube](https://github.com/tomaj/hermes/releases).

## Staňte sa prispievateľom

Hlásenia chýb, opravy dokumentácie aj pull requesty sú vítané. Stránka [Prispievanie](/sk/contributing) uvádza kontroly, ktorými musí pull request prejsť.

## Časté otázky

### Kto spravuje Hermes?

Tomáš Majer ([@tomaj](https://github.com/tomaj)) projekt vytvoril a spravuje ho.

### Ako sa stať prispievateľom?

Otvorte pull request z feature vetvy. Stránka [Prispievanie](/sk/contributing) uvádza kontroly, ktorými musí prejsť.

### Odkiaľ pochádza zoznam prispievateľov?

Z GitHub contributors API pre `tomaj/hermes`, uložený pri poslednej aktualizácii dokumentácie.

### Kedy projekt začal?

Prvý commit je z roku 2015. História vydaní je na tejto stránke a v [Zozname zmien](/sk/changelog).

### Kde nahlásiť chybu?

V [issues na GitHube](https://github.com/tomaj/hermes/issues).
