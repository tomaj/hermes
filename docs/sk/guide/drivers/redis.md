---
title: "Driver Redis (ext-redis)"
description: "RedisSetDriver s rozšírením phpredis: kľúče, slučka wait, priority a odložené správy."
---

# Redis (ext-redis)

`Tomaj\Hermes\Driver\RedisSetDriver` komunikuje s inštanciou `Redis` z rozšírenia phpredis. Pripojenie otvárate sami. Trieda `connect()` nevolá.

```php
public function __construct(
    Redis $redis,
    string $key = 'hermes',
    int $refreshInterval = 1,
    string $scheduleKey = 'hermes_schedule'
)
```

Konštruktor zaregistruje `$key` s prioritou `Dispatcher::DEFAULT_PRIORITY` (`100`). `$refreshInterval` je počet sekúnd, ktoré `wait()` spí, keď sú všetky vybrané množiny prázdne. `0` toto spanie vypne a záporná hodnota vyhodí `InvalidArgumentException`. `$scheduleKey` je zoradená množina, ktorú používa [odložené spracovanie](/sk/guide/delayed).

## Vloženie a spracovanie

Z `examples/redis/emitter.php` a `examples/redis/processor.php`:

```php
use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;
use Tomaj\Hermes\Shutdown\RedisShutdown;

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

$driver = new RedisSetDriver($redis);
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter = new Emitter($driver);
$emitter->emit(
    new Message('type1', ['message' => 1]),
    Dispatcher::DEFAULT_PRIORITY + 10
);
```

```php
$driver = new RedisSetDriver($redis, 'hermes', 1);
$dispatcher = new Dispatcher($driver, null, new RedisShutdown($redis));
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`setupPriorityQueue()` musí bežať na driveri emittera aj na driveri workera. Nezdieľajú PHP objekty. Zdieľajú kľúče v Redise. Neznáme priority vyhodia `UnknownPriorityException` so správou `Unknown priority 999` (číslo je to, ktoré ste odovzdali).

Druhý worker len pre jednu prioritu:

```php
$dispatcher->handle([Dispatcher::DEFAULT_PRIORITY + 10]);
```

Argument je celočíselná priorita, nie názov kľúča `hermes_high`.

Hermes nepoužíva Redis list. Živé správy sú prvky množiny. Odložené správy sú prvky zoradenej množiny. Každé číslo priority má vlastný kľúč množiny. Predvolený kľúč je `hermes` s prioritou `100`. Kľúč plánovača je predvolene `hermes_schedule`.

<Diagram name="drivers-redis-1" alt="Redis send: budúce správy idú do zoradenej množiny hermes_schedule, ostatné do množiny svojej priority" />

`SPOP` odstráni jeden náhodný prvok. Nie je to `LPOP` a nie je to FIFO. Názvy ako `hermes_high` sú to, čo ste odovzdali `setupPriorityQueue()`. Čísla vyššie sú tie z `examples/redis`.

## Čo zapisuje `send()`

```php
if ($message->getExecuteAt() !== null && $message->getExecuteAt() > microtime(true)) {
    $this->redis->zAdd($this->scheduleKey, $message->getExecuteAt(), $serialized);
} else {
    $this->redis->sAdd($this->getKey($priority), $serialized);
}
```

`zAdd` má najprv skóre a potom prvok, čo je poradie argumentov v phpredis. Živá štruktúra je množina (`SADD`), nie list.

## Čo robí `wait()`

<Diagram name="drivers-redis-2" alt="Slučka wait v Redise: prečíta splatné záznamy plánu, urobí SPOP z prvej neprázdnej množiny priority a zavolá handler" />

Pri prázdnom prechode, a keď `$refreshInterval` nie je `0`, driver spí daný počet sekúnd. Ukončenie kontroluje pred spaním aj na začiatku slučky.

`zRangeByScore` si vypýta jeden splatný prvok. `zRem()` ho potom odstráni a znova ho pošle len worker, ktorému `ZREM` vrátil `1`, takže paralelné workery ho nezdvoja. Vo verzii 5.x tento krok volal `sPop()` na zoradenú množinu a splatné správy ju nikdy neopustili ([issue #95](https://github.com/tomaj/hermes/issues/95)). Pozrite [Odložené spracovanie](/sk/guide/delayed).

Čítanie priorít zoradí fronty cez `krsort($queues, SORT_NUMERIC)`, takže najvyššie číslo priority sa číta ako prvé bez ohľadu na poradie registrácie. Verzia 5.x namiesto toho otáčala poradie vkladania ([issue #50](https://github.com/tomaj/hermes/issues/50)). Pozrite [Priorita](/sk/guide/priority).

`wait()` odovzdá callbacku dispatchera prioritu, z ktorej správu vybral. Návratová hodnota callbacku sa ignoruje. `incrementProcessedItems()` beží po návrate callbacku. `ShutdownException` vyhodená v callbacku toto zvýšenie preskočí a ukončí `handle()`.

::: warning Poškodená správa sa stratí
`spop` odstráni správu z množiny priority a `zrem` odloženú správu z plánu ešte pred deserializáciou. Ak telo nejde deserializovať, `wait()` vyhodí `SerializeException`, správa už v Redise nie je a výnimka telo neobsahuje. Worker sa zastaví. Hermes takú správu neobnoví, preto vkladajte správy len cez Hermes alebo serializér, ktorý máte pod kontrolou, a správy, o ktoré nesmiete prísť, si uložte aj inde.
:::

## Traity

K dispozícii sú `setSerializer()`, `setMaxProcessItems()` a `setShutdown()`. Odovzdanie objektu na ukončenie `Dispatcher`u za vás zavolá aj `setShutdown()`.

## Časté otázky

### Pripojí sa driver k Redisu za mňa?

Nie. Vytvorte objekt `Redis`, zavolajte `connect()` a odovzdajte ho konštruktoru.

### Prečo sa moje odložené správy nikdy nespustia?

Vo verzii 5.x krok presunu volá `SPOP` na zoradený kľúč plánovača, takže splatné správy v ňom zostanú. Prejdite na 6.0 alebo vo verzii 5.x použite `PredisSetDriver`. Pozrite [Odložené spracovanie](/sk/guide/delayed).

### Spracúvajú sa správy v poradí?

Nie. Každá priorita je množina v Redise a `SPOP` vracia náhodný prvok.

### Čo robí interval obnovy?

Je to počet sekúnd, ktoré `wait()` spí, keď sú všetky fronty prázdne. `0` znamená bez spania, čo zamestnáva CPU.

### Môžem tu použiť klienta Predis?

Nie. Pre Predis použite `PredisSetDriver`. Tieto dva drivery sa rozdelili vo verzii 4.0.0.
