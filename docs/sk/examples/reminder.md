---
title: "Odložená pripomienka"
description: "Naplánujte e-mail s pripomienkou na neskôr pomocou executeAt v Hermese s Predisom."
---

# Odložená pripomienka

**Cieľ:** poslať e-mail s pripomienkou deň pred rezerváciou. Správa sa vloží pri vytvorení rezervácie s `executeAt` nastaveným na čas pripomienky.

<ProjectSource name="reminder" :files="['src/ReminderHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />


<Diagram name="examples-reminder-1" alt="Rezervácia vloží správu s executeAt. Predis ju cez ZADD pridá do hermes_schedule, v čase splatnosti ju presunie do živej fronty a ReminderHandler pošle zákazníkovi e-mail, ak rezervácia stále existuje." />

## Súbory

Redis, Mailpit a `src/Hermes.php` ako v [návode na uvítací e-mail](/sk/examples/welcome-email).

<<< @/../examples/reminder/composer.json{json} [composer.json]

<<< @/../examples/reminder/public/index.php{php} [public/index.php]

<<< @/../examples/reminder/src/ReminderHandler.php{php} [src/ReminderHandler.php]

<<< @/../examples/reminder/bin/worker.php{php} [bin/worker.php]

## Spustenie

`REMIND_IN` prepíše oneskorenie, aby ste nemuseli čakať deň:

```bash
composer install
docker compose up -d
php bin/worker.php                    # terminal 1
REMIND_IN=10 php public/index.php     # terminal 2
docker compose exec redis redis-cli zrange hermes_schedule 0 -1 withscores
```

Správa čaká v `hermes_schedule` so skóre rovným času pripomienky. Asi o desať sekúnd ju worker presunie do živej fronty, spracuje ju a v Mailpite na `http://localhost:8025` sa objaví „See you tomorrow“.

## Úskalia

- **Vo verzii 5.x spoľahlivo odkladá len Predis.** `RedisSetDriver` (ext-redis) v 5.x zapisuje do zoradenej množiny, ale splatné správy nikdy nevráti ([#95](https://github.com/tomaj/hermes/issues/95), opravené v 6.0). RabbitMQ, SQS a Kafka ignorujú `executeAt` a doručia hneď. Pozrite [Odložené spracovanie](/sk/guide/delayed).
- **Stav kontrolujte v čase spustenia správy.** Deň je dlhý: rezervácia sa môže zrušiť alebo presunúť. V handleri ju načítajte znova a ak už neplatí, nerobte nič.
- **Musí bežať worker.** Splatné správy sa presúvajú len vo `wait()`. Ak v čase pripomienky žiadny worker nebeží, pripomienka príde neskoro, ale nestratí sa.
- **`executeAt` je Unix časová značka v sekundách ako float.** Počítajte ju v UTC, napríklad cez `strtotime()` alebo `DateTimeImmutable::getTimestamp()`, aby ju časové pásma nemohli posunúť.
- **Pripomienku nemeňte úpravou správy.** Vložte novú a nech handler staré ignoruje, napríklad uložením id aktuálnej pripomienky k rezervácii.

## Celý zdrojový kód

Všetky súbory tohto receptu, s `README.md`, `Makefile` a kontrolou `e2e.sh`, sú v [`examples/reminder` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/reminder). Naklonujte repozitár, spustite `cd examples/reminder` a `make install up`, potom `make e2e`.

## Časté otázky

### Môžem na pripomienky použiť ext-redis?

Áno, od 6.0, ktorú tieto recepty vyžadujú. Vo verzii 5.x jeho krok presunu necháva splatné správy v zoradenej množine, preto tam použite Predis. Pozrite [Odložené spracovanie](/sk/guide/delayed).

### Čo ak sa rezervácia zruší?

Handler ju načíta znova a ak už neplatí, nerobí nič.

### Čo ak v čase pripomienky nebeží žiadny worker?

Pripomienka sa spustí neskôr, hneď ako sa worker spustí. Nestratí sa.

### Ako pripomienku presunúť?

Vložte novú správu a nech handler starú ignoruje, napríklad uložením id aktuálnej pripomienky.

### V akých jednotkách je `executeAt`?

Unix časová značka v sekundách ako float.
