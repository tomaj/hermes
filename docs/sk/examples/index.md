---
title: "Príklady"
description: "Kompletné spustiteľné recepty pre Hermes: uvítací e-mail, faktúra v PDF, webhooky, import CSV, náhľady, pripomienky a priority."
---

# Príklady

Kompletné recepty, ktoré môžete skopírovať do prázdneho priečinka a spustiť. Každý má cieľ, diagram, všetky súbory, príkazy na spustenie a kontrolu a úskalia, na ktorých pri danej úlohe záleží.

Začnite návodom. Vysvetľuje každý krok a ostatné recepty stavajú na jeho nastavení.

| Recept | Driver | Ukazuje |
| --- | --- | --- |
| [Uvítací e-mail od nuly](/sk/examples/welcome-email) | Predis | Návod: inštalácia, vloženie, handler, worker, Mailpit, opakovanie, testy, Supervisor |
| [Faktúra v PDF](/sk/examples/invoice-pdf) | Predis | Idempotentný handler, ktorý zapisuje súbory |
| [Webhooky s opakovaním](/sk/examples/webhooks) | Predis | Podpísané požiadavky, ktoré zlyhania opakovať, oneskorenie |
| [Import CSV po častiach](/sk/examples/csv-import) | Predis | Rozdelenie veľkej úlohy na bezpečné paralelné časti |
| [Náhľady obrázkov](/sk/examples/thumbnails) | RabbitMQ | Čo sa mení na brokeri bez oneskorenia a s potvrdzovaním po handleri |
| [Odložená pripomienka](/sk/examples/reminder) | Predis | `executeAt` a kontrola stavu v čase spustenia správy |
| [Prioritné fronty](/sk/examples/priority) | Predis | Poradie priorít a worker len pre urgentnú prácu |

Každý recept vyžaduje `tomaj/hermes:dev-master`, pripravovanú verziu 6.0, a PHP 8.4 alebo novšie. Kód bol spustený proti Redisu 8, RabbitMQ 4 a Mailpitu.

Každý recept je spustiteľný projekt v [`examples/`](https://github.com/tomaj/hermes/tree/master/examples) s `Makefile` a `e2e.sh`, ktorý overí celý postup. Kód na týchto stránkach sa pri zostavení načítava z týchto súborov, takže je to presne ten kód, ktorý kontroly spúšťajú. CI spúšťa každé `e2e.sh` aj integrácie do frameworkov proti kódu každého pull requestu.

## Časté otázky

### Sú príklady testované?

Áno. Workflow `Examples` spúšťa `e2e.sh` každého receptu (vrátane `filesystem` a `emit-after-commit`, ktoré nepotrebujú služby, a `kafka`) s Redisom, RabbitMQ, Kafkou a Mailpitom podľa potreby a [integrácie do frameworkov](/sk/frameworks/) v čistých projektoch Laravel, Symfony a Nette s Redisom, Predisom, RabbitMQ a SQS. Hermes inštaluje z pull requestu cez Composer path repository, takže zmena, ktorá recept pokazí, pull request zlyhá. [`examples/README.md`](https://github.com/tomaj/hermes/blob/master/examples/README.md#running-the-checks-against-this-checkout) ukazuje, ako spustiť tie isté skripty lokálne.

### Používajú príklady vydaný balík?

Zatiaľ nie. Kým Hermes 6.0 nemá tag, každý vyžaduje `tomaj/hermes:dev-master` z Packagistu s `"minimum-stability": "dev"` a `"prefer-stable": true`, takže z vetvy pochádza len samotný Hermes. Po vydaní bude obmedzenie `^6.0`. Ak ste recept s náhľadmi spúšťali na 5.x, najprv zmažte jeho frontu `thumbnails`: 6.0 ju deklaruje ako durable a RabbitMQ zmenenú deklaráciu odmietne.

### Akú verziu PHP príklady potrebujú?

PHP 8.4 alebo novšie, rovnako ako Hermes 6.0.

### Potrebujem Docker?

Len pre Redis, Mailpit a RabbitMQ. Funguje aj akýkoľvek lokálny Redis alebo RabbitMQ na predvolených portoch.

### Prečo väčšina príkladov používa Predis?

Nepotrebuje PHP rozšírenie. Vo verzii 5.x to bol aj jediný klient Redisu, ktorý presúval odložené správy a opakovania; od 6.0 to robí aj ext-redis. Pozrite [Odložené spracovanie](/sk/guide/delayed).

### Kde je spustiteľný kód?

V `examples/` v repozitári, s `Makefile` a `e2e.sh` pre každý projekt.
