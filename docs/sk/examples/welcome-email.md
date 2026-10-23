---
title: "Návod na uvítací e-mail"
description: "Krok za krokom od prázdneho priečinka: pošlite uvítací e-mail z PHP workera na pozadí s Hermesom, Redisom a Mailpitom."
howto:
  totalTime: PT15M
  tools: [PHP 8.4 alebo novšie, Composer, Docker s Compose]
---

# Uvítací e-mail od nuly

Tento návod zostaví malú aplikáciu od prázdneho priečinka. Registračný formulár vloží správu, worker ju vyberie a pošle uvítací e-mail a vy sledujete, ako e-mail dorazí do [Mailpitu](https://mailpit.axllent.org/). Potom zámerne vyradíte SMTP a uvidíte, ako Hermes opakuje, a nakoniec workera spustíte pod Supervisorom.

Trvá to asi 15 minút. Každý súbor je kompletný, takže každý blok môžete skopírovať tak, ako je.

<ProjectSource name="welcome-email" :files="['src/WelcomeEmailHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />


<Diagram name="examples-welcome-email-1" alt="Formulár odošle dáta do index.php, ktorý vloží správu do Redisu. bin/worker.php ju vyberie a WelcomeEmailHandler pošle e-mail do Mailpitu. Keď odoslanie vyhodí výnimku, kópia na opakovanie ide späť do Redisu." />

## Čo potrebujete

- PHP 8.4 alebo novšie s Composerom
- Docker s Compose pre Redis a Mailpit

Aplikácia používa `PredisSetDriver`. Predis je obyčajný PHP balík, takže netreba inštalovať žiadne rozšírenie. Je to aj Redis driver, ktorý cez `ZREM` vracia odložené správy a opakovania do fronty, čo krok s opakovaním nižšie potrebuje. Pozrite [Odložené spracovanie](/sk/guide/delayed).

## 1. Vytvorte projekt

```bash
mkdir welcome-email && cd welcome-email
mkdir -p public src bin tests deploy
```

<<< @/../examples/welcome-email/composer.json{json} [composer.json]

```bash
composer install
```

Tým sa nainštaluje Hermes z `dev-master` (6.0, kým nemá tag), Predis, Symfony Mailer na SMTP a Monolog, aby worker vypisoval, čo robí.

## 2. Spustite Redis a Mailpit

<<< @/../examples/welcome-email/docker-compose.yml{yaml} [docker-compose.yml]

```bash
docker compose up -d
```

Otvorte `http://localhost:8025`. Schránka v Mailpite je prázdna. Mailpit prijme akýkoľvek e-mail na porte 1025 a zobrazí ho v prehliadači a nič neopustí váš počítač.

## 3. Jedno miesto pre driver

Webová aplikácia aj worker musia používať ten istý driver s rovnakými kľúčmi. Nastavenie dajte do jednej triedy.

<<< @/../examples/welcome-email/src/Hermes.php{php} [src/Hermes.php]

`'hermes'` je fronta, `1` je počet sekúnd, ktoré worker spí pri prázdnej fronte, a `'hermes_schedule'` je zoradená množina pre odložené správy a opakovania.

## 4. Vložte správu z controllera

Toto je webová strana. Overí formulár, uložila by používateľa a vloží správu `user.registered`. Vloženie je jeden príkaz v Redise, takže odpoveď je okamžitá. E-mail sa tu neposiela.

<<< @/../examples/welcome-email/public/index.php{php} [public/index.php]

## 5. Napíšte handler

Handler robí pomalú prácu v procese workera. `RetryTrait` dá dispatcheru vedieť, že tento handler chce opakovanie, a `maxRetry()` ho obmedzí na päť pokusov.

<<< @/../examples/welcome-email/src/WelcomeEmailHandler.php{php} [src/WelcomeEmailHandler.php]

## 6. Napíšte workera

Worker zaregistruje handler pre typ `user.registered` a zavolá `handle()`, ktoré beží v slučke, kým ho niekto nezastaví.

<<< @/../examples/welcome-email/bin/worker.php{php} [bin/worker.php]

A malý skript na zastavenie všetkých workerov, ktorý neskôr použijete pri nasadení:

<<< @/../examples/welcome-email/bin/restart.php{php} [bin/restart.php]

## 7. Spustite to

V priečinku projektu otvorte dva terminály.

```bash
# terminal 1: the worker
php bin/worker.php
```

```bash
# terminal 2: the web app
php -S localhost:8000 -t public
```

Otvorte `http://localhost:8000`, zadajte meno a e-mailovú adresu a stlačte Register. Alebo z tretieho terminálu:

```bash
curl -d 'name=Jane&email=jane@example.com' http://localhost:8000/
```

Worker vypíše:

```text
worker.INFO: Start handle message #57e9ddf5-… (user.registered) priority:100
worker.INFO: End handle message #57e9ddf5-… (user.registered)
```

Obnovte `http://localhost:8025`. E-mail „Welcome aboard!“ pre jane@example.com je tam.

## 8. Sledujte opakovanie

Zastavte Mailpit, aby SMTP nefungovalo, a zaregistrujte sa znova:

```bash
docker compose stop mailpit
curl -d 'name=Bob&email=bob@example.com' http://localhost:8000/
```

Handler vyhodí výnimku a worker ju zaloguje:

```text
worker.ERROR: Handler App\WelcomeEmailHandler throws exception - …
```

Keďže handler používa `RetryTrait`, dispatcher poslal kópiu správy s `retries` nastaveným na 1 a s `executeAt` 16 až 45 sekúnd v budúcnosti. Čakajúcu ju uvidíte v Redise:

```bash
docker compose exec redis redis-cli zrange hermes_schedule 0 -1
```

Znova spustite Mailpit:

```bash
docker compose start mailpit
```

Do minúty worker zaloguje druhé `Start handle message` s tým istým id a `"retries":1` a e-mail pre bob@example.com sa objaví v Mailpite. Mailpit drží správy v pamäti, takže predchádzajúci e-mail po reštarte zmizol.

Oneskorenie s každým pokusom rastie: `retries⁴ + 15 + jitter × (retries + 1)` sekúnd, s náhodnou zložkou od 1 do 30. Päť opakovaní pokryje približne desať minút výpadku SMTP. Pri dlhších výpadkoch zvýšte `maxRetry()`. Pozrite [Opakovanie](/sk/guide/retry).

## 9. Otestujte handler

Handler je obyčajná trieda, takže ho otestujete bez Redisu aj bez workera. `composer.json` z kroku 1 už má PHPUnit v `require-dev` a v `autoload-dev` mapuje `App\Tests\` na `tests/`, takže `composer install` nastavil oboje.

<<< @/../examples/welcome-email/tests/WelcomeEmailHandlerTest.php{php} [tests/WelcomeEmailHandlerTest.php]

```bash
vendor/bin/phpunit tests
```

## 10. Spustite to pod Supervisorom

V produkcii udržiava workera v behu správca procesov a keď skončí, spustí nový. Worker zámerne skončí v dvoch prípadoch: po 1000 správach (`setMaxProcessItems`) a po `php bin/restart.php`.

<Diagram name="examples-welcome-email-2" alt="Supervisor spúšťa worker_00 a worker_01, oba čítajú Redis. php bin/restart.php nastaví kľúč hermes_shutdown." />

<<< @/../examples/welcome-email/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

Skopírujte ho na miesto a cesty upravte podľa svojho servera:

```bash
sudo cp deploy/hermes-worker.conf /etc/supervisor/conf.d/
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

```text
hermes-worker:hermes-worker_00   RUNNING   pid 1643852, uptime 0:00:03
hermes-worker:hermes-worker_01   RUNNING   pid 1643858, uptime 0:00:03
```

Po každom nasadení spustite:

```bash
php bin/restart.php
```

`PredisShutdown` zapíše aktuálny čas do kľúča `hermes_shutdown`. Každý worker, ktorý sa spustil pred týmto časom, dokončí aktuálnu správu, zaloguje `Exiting hermes dispatcher - shutdown` a skončí. Supervisor spustí nové workery a tie načítajú nový kód. Workery, ktoré sa spustia po signáli, ho ignorujú.

So systemd namiesto Supervisora beží ten istý worker ako šablóna jednotky:

```ini
# /etc/systemd/system/hermes-worker@.service
[Unit]
Description=Hermes worker %i
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/welcome-email
Environment=REDIS_URL=tcp://127.0.0.1:6379
Environment=MAILER_DSN=smtp://127.0.0.1:1025
ExecStart=/usr/bin/php bin/worker.php
Restart=always
RestartSec=1

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now hermes-worker@1 hermes-worker@2
```

## Úskalia

- **Správa zmizne z Redisu ešte pred spustením handlera.** `SPOP` ju odstráni ako prvý. Ak proces workera zomrie uprostred `handle()`, e-mail sa nepošle a ani sa neopakuje. Opakuje sa len vtedy, keď handler vyhodí výnimku.
- **Vrátenie `false` neopakuje.** Opakuje len výnimka. Vyhoďte ju, keď by neskorší pokus mohol uspieť.
- **Emitter a worker sa musia zhodovať.** Rovnaká trieda drivera, rovnaký kľúč fronty, rovnaký kľúč plánovača. `src/Hermes.php` existuje práve preto, aby sa nerozišli.
- **Dlho bežiace PHP si drží stav.** Worker vytvorí mailer raz. Zastarané SMTP pripojenie, hodnota konfigurácie v cache alebo pomalý únik pamäte zostanú, kým proces neskončí, a preto sa oplatí `setMaxProcessItems` aj reštart cez Supervisor.
- **Vo verzii 5.x opakovanie potrebuje Predis.** S `RedisSetDriver` (ext-redis) vo verzii 5.x sa odložené správy a opakovania zapíšu do zoradenej množiny, ale nikdy sa nevrátia do fronty. Pozrite [Odložené spracovanie](/sk/guide/delayed).
- **Po nasadení reštartujte.** Worker spustený pred nasadením stále beží so starým kódom. Ako posledný krok nasadenia spustite `php bin/restart.php`.

## Celý zdrojový kód

Všetky súbory tohto receptu, s `README.md`, `Makefile` a kontrolou `e2e.sh`, sú v [`examples/welcome-email` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/welcome-email). Naklonujte repozitár, spustite `cd examples/welcome-email` a `make install up`, potom `make e2e`.

## Časté otázky

### Prečo e-mail nedorazí?

Skontrolujte, či worker beží a či Mailpit počúva na porte 1025. Log workera ukazuje `Start handle message` pre každú správu, ktorú vyberie.

### Môžem namiesto Mailpitu použiť skutočný SMTP server?

Áno. Pre workera nastavte `MAILER_DSN`, napríklad `smtp://user:pass@smtp.example.com:587`.

### Prečo Predis a nie ext-redis?

Nepotrebuje PHP rozšírenie. Opakovanie potrebuje cestu pre odložené správy, ktorú vo verzii 5.x správne presúva len Predis; od 6.0 funguje aj ext-redis. Pozrite [Odložené spracovanie](/sk/guide/delayed).

### Koľkokrát sa neúspešný e-mail opakuje?

S `maxRetry()` tohto handlera päťkrát, počas približne desiatich minút. Pozrite [Opakovanie](/sk/guide/retry).

### Ako nasadiť nový kód?

Po nasadení spustite `php bin/restart.php`. Supervisor znova spustí workery s novým kódom. Pozrite [Ukončenie](/sk/guide/shutdown).
