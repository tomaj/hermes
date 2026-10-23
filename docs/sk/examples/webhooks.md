---
title: "Webhooky s opakovaním"
description: "Doručujte podpísané webhooky z PHP cez Hermes, opakujte 5xx a 429 s rastúcim oneskorením a pri ostatných chybách to vzdajte."
---

# Webhooky s opakovaním

**Cieľ:** posielať podpísané udalosti cez POST na URL vašich zákazníkov. Keď príjemca nefunguje, opakovať s rastúcim oneskorením. Pri odpovediach, ktoré sa nezlepšia, to vzdať.

<ProjectSource name="webhooks" :files="['src/WebhookHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />


<Diagram name="examples-webhooks-1" alt="order.shipped sa vloží do Redisu. WebhookHandler ho pošle cez POST príjemcovi. 2xx je doručené, iné 4xx vráti false a 5xx alebo 429 vyhodí výnimku, takže kópia na opakovanie ide späť do Redisu." />

## Súbory

Redis a `src/Hermes.php` ako v [návode na uvítací e-mail](/sk/examples/welcome-email).

<<< @/../examples/webhooks/composer.json{json} [composer.json]

<<< @/../examples/webhooks/public/index.php{php} [public/index.php]

<<< @/../examples/webhooks/src/WebhookHandler.php{php} [src/WebhookHandler.php]

<<< @/../examples/webhooks/bin/worker.php{php} [bin/worker.php]

Falošný príjemca na lokálne testovanie. Na prvý pokus odpovie `503` a na druhý `204`:

<<< @/../examples/webhooks/receiver.php{php} [receiver.php]

## Spustenie

```bash
composer install
docker compose up -d redis
php -S 127.0.0.1:9000 receiver.php     # terminal 1: the receiver
php bin/worker.php                     # terminal 2: the worker
php public/index.php                   # terminal 3: emit one event
```

Príjemca zaloguje prvý pokus hneď a druhý o 16 až 45 sekúnd neskôr, s tým istým `Webhook-Id`:

```text
webhook d8299c53-… attempt 1 signature ok
webhook d8299c53-… attempt 2 signature ok
```

Worker medzi nimi zaloguje `Handler App\WebhookHandler throws exception - Receiver answered 503`.

## Úskalia

- **Rozhodnite, ktoré zlyhania opakovať.** Výnimka opakuje, vrátenie `false` nie. Timeout, `5xx` alebo `429` môžu neskôr uspieť. `404` ani `410` nie.
- **Posielajte stabilné id.** Dispatcher ponechá id správy na každej kópii na opakovanie. Posielajte ho v hlavičke, aby príjemcovia mohli zahodiť duplicity, pretože timeout môže skryť požiadavku, ktorá dorazila.
- **Oneskorenie opakovania určuje Hermes.** Je to `retries⁴ + 15 + jitter × (retries + 1)` sekúnd, s náhodnou zložkou od 1 do 30. S `maxRetry()` 8 je posledný pokus asi hodinu a pol po prvom.
- **Opakovanie potrebuje driver s oneskorením.** Na RabbitMQ, SQS a Kafka sa kópia publikuje hneď, takže všetky pokusy prebehnú v priebehu sekúnd. Na webhooky použite Predis.
- **Nastavte timeout.** Jeden pomalý príjemca nesmie zablokovať workera. Desať sekúnd stačí a viac workerov udrží ostatné doručenia v pohybe.

## Celý zdrojový kód

Všetky súbory tohto receptu, s `README.md`, `Makefile` a kontrolou `e2e.sh`, sú v [`examples/webhooks` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/webhooks). Naklonujte repozitár, spustite `cd examples/webhooks` a `make install up`, potom `make e2e`.

## Časté otázky

### Ktoré odpovede sa opakujú?

Timeouty, 5xx a 429 vyhodia výnimku, takže sa opakujú. Ostatné 4xx vrátia `false` a neopakujú sa.

### Ako príjemcovia zahodia duplicity?

Podľa hlavičky `Webhook-Id`. Je to id správy, ktoré zostáva rovnaké pri každom opakovaní.

### Ako príjemcovia overia podpis?

Vypočítajú `hash_hmac('sha256', timestamp . '.' . body, secret)` a porovnajú ho cez `hash_equals()`, ako to robí `receiver.php`.

### Ako dlho to Hermes skúša?

S `maxRetry()` 8 asi hodinu a pol. Vzorec je na stránke [Opakovanie](/sk/guide/retry).

### Môžem na webhooky použiť RabbitMQ?

Nie s rastúcim oneskorením. RabbitMQ ignoruje `executeAt`, takže každé opakovanie prebehne hneď.
