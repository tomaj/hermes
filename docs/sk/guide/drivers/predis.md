---
title: "Driver Predis"
description: "PredisSetDriver pre Hermes: Redis bez PHP rozšírenia, odložené správy, priority a ukončenie."
---

# Predis

`Tomaj\Hermes\Driver\PredisSetDriver` používa rovnaké rozloženie v Redise ako `RedisSetDriver`, ale s `Predis\Client` a názvami metód z Predisu. Túto triedu použite, keď nemôžete načítať `ext-redis`. Je testovaná s Predis 2.3 a novším a s Predis 3 (`predis/predis: ^2.3 || ^3.0`). Klienta Predis neodovzdávajte `RedisSetDriver`. Rozdelenie vo verzii 4.0.0 z nich urobilo samostatné typy.

```php
public function __construct(
    Client $redis,
    string $key = 'hermes',
    int $refreshInterval = 1,
    string $scheduleKey = 'hermes_schedule'
)
```

## Vloženie a spracovanie

Toto je `examples/predis`:

```php
use Predis\Client;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\PredisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Shutdown\PredisShutdown;

$redis = new Client([
    'scheme' => 'tcp',
    'host' => '127.0.0.1',
    'port' => 6379,
]);

$driver = new PredisSetDriver($redis);
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter = new Emitter($driver);
$emitter->emit(new Message('type1', ['message' => 1]), Dispatcher::DEFAULT_PRIORITY);
```

```php
$driver = new PredisSetDriver($redis, 'hermes', 1);
$dispatcher = new Dispatcher($driver, null, new PredisShutdown($redis));
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`examples/predis/shutdown.php` zastaví workery takto:

```php
new PredisShutdown($redis)->shutdown();
```

## Príkazy

Splatná správa alebo správa bez `executeAt` sa vloží cez `sadd` do kľúča priority. Budúca správa takto:

```php
$this->redis->zadd($this->scheduleKey, [
    $this->serializer->serialize($message) => $message->getExecuteAt(),
]);
```

Táto mapa prvok → skóre je tvar, ktorý Predis očakáva. Je to oprava [issue #48](https://github.com/tomaj/hermes/issues/48), kde sa opakovanie plánovalo s argumentmi naopak.

`send()` berie `executeAt` ako „odlož to“, len keď je hodnota pravdivá **a** väčšia ako `microtime(true)`. Hodnota `null` sa neodkladá.

## Slučka wait

<Diagram name="drivers-predis-1" alt="Slučka wait v Predise: ZRANGEBYSCORE a ZREM presunú splatné správy, potom SPOP vyberie jednu z množín priorít" />

`zrangebyscore` číta od `-inf` po aktuálny čas, odovzdaný ako reťazec s mikrosekundami (`sprintf('%.6F', microtime(true))`), pretože Predis 2 a 3 typujú hranicu ako `int|string`. Používa `LIMIT OFFSET 0 COUNT 1`. Každý vrátený prvok sa odstráni cez `zrem`. Späť cez `send()` sa pošle, len keď `zrem` vráti `1`, teda keď ho odstránil tento worker; worker, ktorý súboj prehral, ho preskočí. `zrangebyscore` vracia len splatné prvky a `send()` porovná `executeAt` s časom načítaným až po tomto dopyte, takže každý z nich cez `sadd` vloží do kľúča **predvolenej** priority, pretože `send()` sa volá bez argumentu priority.

Živá fronta je stále množina. `spop` nie je FIFO. Prázdne slučky spia `$refreshInterval` sekúnd, keď táto hodnota nie je `0`. Záporné `$refreshInterval` vyhodí v konštruktore `InvalidArgumentException`.

::: warning Poškodená správa sa stratí
`spop` odstráni správu z množiny priority a `zrem` odloženú správu z plánu ešte pred deserializáciou. Ak telo nejde deserializovať, `wait()` vyhodí `SerializeException`, správa už v Redise nie je a výnimka telo neobsahuje. Worker sa zastaví. Hermes takú správu neobnoví, preto vkladajte správy len cez Hermes alebo serializér, ktorý máte pod kontrolou, a správy, o ktoré nesmiete prísť, si uložte aj inde.
:::

Poradie priorít je rovnaké ako pri ext-redis: najvyššie číslo ako prvé. Pozrite [Priorita](/sk/guide/priority).

Kontroly ukončenia používajú `ShutdownTrait`. Tento driver kombinujte s `PredisShutdown`, nie s `RedisShutdown`. `PredisShutdown::shutdown()` považuje stavový payload Predisu `OK` za úspech. `shouldShutdown()` považuje chýbajúci kľúč (`null` z `get`) za „pokračovať“.

## Časté otázky

### Kedy zvoliť Predis namiesto ext-redis?

Keď nemôžete nainštalovať rozšírenie phpredis. Vo verzii 5.x aj vtedy, keď potrebujete odložené správy a opakovanie, ktoré tam správne presúva len Predis; od 6.0 ich presúva aj `RedisSetDriver`.

### Je rozloženie dát v Redise rovnaké?

Áno. Množiny pre jednotlivé priority a jedna zoradená množina pre plán, takže názvy kľúčov aj správanie zodpovedajú `RedisSetDriver`.

### Ktoré verzie Predisu sú podporované?

Predis 2.3 a novší a Predis 3. Testy bežia proti najnižšej (2.3.0) aj najnovšej verzii. Predis 1.x a 2.0 až 2.2 vypisujú deprecations na PHP 8.4 a už sa netestujú. `composer.json` uvádza `predis/predis` `<2.3` v sekcii `conflict`, takže Composer odmietne nainštalovať Hermes 6 vedľa staršieho Predisu.

### Ktorá trieda na ukončenie k nemu patrí?

`PredisShutdown`. `RedisShutdown` očakáva objekt `Redis` z phpredis.

### Fungujú priority rovnako?

Áno. Najvyššie číslo sa číta ako prvé. Verzia 5.x používala opačné poradie registrácie, issue [#50](https://github.com/tomaj/hermes/issues/50). Pozrite [Priorita](/sk/guide/priority).

### Existuje kompletný príklad s Predisom?

Áno. Všetky [príklady](/sk/examples/) okrem receptu s RabbitMQ používajú Predis, počnúc [návodom na uvítací e-mail](/sk/examples/welcome-email).
