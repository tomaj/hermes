---
title: "Začíname"
description: "Nainštalujte Hermes 6.x, vložte správu z webovej požiadavky a spustite PHP workera, ktorý ju spracuje."
---

# Začíname

Hermes 6.x je PHP knižnica. Nemá vlastný spustiteľný súbor. Webová požiadavka vytvorí `Message` a odovzdá ju `Emitter`u. Samostatný PHP CLI proces vytvorí `Dispatcher`, zaregistruje handlery a zavolá `handle()`, ktoré blokuje.

<Diagram name="getting-started-1" alt="Postup: aplikácia vloží správu, emitter ju pošle driveru a dispatcher čaká, prijme ju a zavolá handler" />

Obmedzenie balíka v `composer.json` je `php: ^8.4`. CI beží na PHP 8.4, na PHP 8.4 s najnižšími verziami závislostí a na PHP 8.5.

## Inštalácia

```bash
composer require tomaj/hermes
```

Tým sa nainštaluje `ramsey/uuid` (`^4.7`) a `psr/log` (`^2 || ^3`). `tracy/tracy` je voliteľná: keď ju nainštalujete a zapnete, Hermes do nej zapisuje výnimky. Klienta pre frontu si vyberáte sami:

| Driver | Čo nainštalovať |
| --- | --- |
| `RedisSetDriver` | Rozšírenie [phpredis](https://github.com/phpredis/phpredis) (`ext-redis`). Composer rozšírenie nenainštaluje. |
| `PredisSetDriver` | `composer require predis/predis` (Predis 2.3 alebo novší, prípadne 3.x) |
| `LazyRabbitMqDriver` | `composer require php-amqplib/php-amqplib` |
| `AmazonSqsDriver` | `composer require aws/aws-sdk-php` |

Logovanie je voliteľné. Funguje ľubovoľný PSR-3 logger. Bežná voľba je [Monolog](https://github.com/Seldaek/monolog), ktorý je v `suggest`, nie v `require`.

## Rýchly štart

Toto je driver pre rozšírenie Redis, rovnako ako v `examples/redis`. Tretí argument konštruktora `Message` je id správy, nie priorita. Priorita je druhý argument `emit()`.

```php
<?php

declare(strict_types=1);

use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Message;

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$driver = new RedisSetDriver($redis);
$emitter = new Emitter($driver);

$emitter->emit(new Message('send-email', [
    'to' => 'test@test.com',
    'subject' => 'Testing hermes email',
    'message' => 'Hello from hermes!',
]));
```

`RedisSetDriver` zaregistruje kľúč `hermes` s prioritou `Dispatcher::DEFAULT_PRIORITY` (`100`). `emit()` bez druhého argumentu použije tú istú konštantu.

Worker je v inom súbore a spúšťa sa príkazom `php worker.php`. `EchoHandler` je súčasťou knižnice. Vypíše id, typ a `json_encode()` payloadu a vráti `true`.

```php
<?php

declare(strict_types=1);

use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Handler\EchoHandler;

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$driver = new RedisSetDriver($redis);
$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('send-email', new EchoHandler());

$dispatcher->handle();
```

`handle()` sa nevráti, kým driver slučku nezastaví (ukončenie, limit správ alebo chyba drivera). Nevolajte ho z webovej požiadavky. To isté hovorí aj docblock `Dispatcher::handle()`.

<img class="theme-img only-light" src="/illustrations/terminal-light.svg" alt="Terminál s výstupom EchoHandler" />
<img class="theme-img only-dark" src="/illustrations/terminal-dark.svg" alt="" />

Výstup `EchoHandler` na stdout vyzerá takto:

```text
Received message: #6f1c0a2e-9b44-4d1a-8c33-0a9e5d77b214 (type send-email)
Payload: {"to":"test@test.com","subject":"Testing hermes email","message":"Hello from hermes!"}
```

Bez loggera sú to jediné vypísané riadky. Pozrite [Logovanie](/sk/guide/logging).

## Vlastný handler

`HandlerInterface` má jednu metódu. Keď sa práca podarila, vráťte `true`. Vrátením `false` označíte volanie ako neúspešné. Výnimka ho tiež označí ako neúspešné a pri handleri s opakovaním (`RetryableHandlerInterface` s `RetryTrait`) naplánuje ďalší pokus.

```php
<?php

declare(strict_types=1);

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class SendEmailHandler implements HandlerInterface
{
    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if ($payload === null) {
            return false;
        }

        mail($payload['to'], $payload['subject'], $payload['message']);

        return true;
    }
}
```

Zaregistrujte ho pod tým istým reťazcom, aký ste použili ako typ správy:

```php
$dispatcher->registerHandler('send-email', new SendEmailHandler());
```

Vrátenie `false` správu do fronty nevráti. Redis driver ju už odstránil cez `SPOP`. Jedinú vstavanú druhú šancu opisuje [Opakovanie](/sk/guide/retry).

## Čo čítať ďalej

- [Pojmy](/sk/guide/concepts): päť typov, ktoré naozaj voláte.
- [Redis](/sk/guide/drivers/redis) alebo [Predis](/sk/guide/drivers/predis) podľa toho, aké pripojenie máte.
- [Nasadenie do produkcie](/sk/guide/deployment), keď má worker prežiť nasadenie.

## Časté otázky

### Potrebuje Hermes vlastný server alebo démona?

Nie. Hermes je PHP knižnica. Váš webový kód vkladá správy a PHP CLI skript, ktorý si napíšete, spúšťa workera cez `Dispatcher::handle()`. Jediná zdieľaná časť je backend fronty: Redis, RabbitMQ, SQS, Kafka alebo lokálny adresár s `FileSystemDriver`.

### S ktorým driverom začať?

Ak nemôžete nainštalovať PHP rozšírenie, použite `PredisSetDriver`. Ak už máte phpredis, použite `RedisSetDriver`. Oba podporujú priority. Vo verzii 5.x vracal odložené správy a opakovania do fronty len Predis; `RedisSetDriver` to robí od 6.0. Na jednom serveri bez brokera použite [`FileSystemDriver`](/sk/guide/drivers/filesystem). Pozrite [porovnanie driverov](/sk/guide/drivers/).

### Môžem volať `handle()` z webovej požiadavky?

Nie. `handle()` blokuje až do ukončenia, limitu správ alebo chyby drivera. Spúšťajte ho v samostatnom CLI procese pod správcom procesov. Pozrite [Nasadenie do produkcie](/sk/guide/deployment).

### Čo má handler vrátiť?

`true`, keď sa práca podarila, a `false`, keď definitívne zlyhala. Ak by neskorší pokus mohol uspieť, vyhoďte výnimku: s `RetryableHandlerInterface` a `RetryTrait` potom dispatcher pošle kópiu na opakovanie. Pozrite [Opakovanie](/sk/guide/retry).

### Existuje kompletný projekt, ktorý môžem skopírovať?

Áno. [Návod na uvítací e-mail](/sk/examples/welcome-email) zostaví aplikáciu od prázdneho priečinka, s Redisom a Mailpitom v Docker Compose, skutočným e-mailovým handlerom, opakovaním, testom a Supervisorom.
