---
title: "Driver RabbitMQ"
description: "LazyRabbitMqDriver pre Hermes: durable fronta, perzistentné publikovanie, manuálne potvrdzovanie a jeho obmedzenia."
---

# RabbitMQ

`Tomaj\Hermes\Driver\LazyRabbitMqDriver` prijme akékoľvek pripojenie php-amqplib (`PhpAmqpLib\Connection\AbstractConnection`). Driver je lazy: kanál otvorí pri prvom `send()` alebo `wait()`, vo vnútri `getChannel()`. Odovzdajte aj lazy pripojenie, aby sa pri vytvorení drivera nepripájalo: `AMQPLazyConnection` ako nižšie, alebo, keďže php-amqplib túto triedu označil za zastaranú, `AMQPConnectionFactory::create()` s `AMQPConnectionConfig::setIsLazy(true)`. Hermes 5.x vyžadoval `AMQPLazyConnection`.

```php
public function __construct(
    AbstractConnection $connection,
    string $queue,
    array $amqpMessageProperties = [],
    int $refreshInterval = 0,
    string $consumerTag = 'hermes',
    bool $durable = true
)
```

`examples/rabbitmq` sa pripája ako predvolený používateľ guest:

```php
use PhpAmqpLib\Connection\AMQPLazyConnection;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\LazyRabbitMqDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;

$connection = new AMQPLazyConnection('localhost', '5672', 'guest', 'guest', '/');
$driver = new LazyRabbitMqDriver($connection, 'hermes_queue');

$emitter = new Emitter($driver);
$emitter->emit(new Message('type1', ['message' => 1]));
```

```php
$driver = new LazyRabbitMqDriver($connection, 'hermes_queue', [], 0);
$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

Je tu jedna fronta a predvolený exchange. Hermes nedeklaruje vlastný exchange. `basic_publish` používa prázdny názov exchange a názov fronty ako routing key, čo je predvolený exchange brokera. `basic_consume` používa manuálne potvrdzovanie: worker pošle `basic_ack` až po dokončení vášho handlera, takže správa spadnutého workera sa vráti do fronty.

<Diagram name="drivers-rabbitmq-1" alt="Cesta publikovania v RabbitMQ: Emitter, basic_publish, predvolený exchange, durable fronta, basic_consume s manuálnym ack, Handler" />

## Fronta a publikovanie

`getChannel()` pri prvom použití deklaruje frontu:

```php
$this->channel->queue_declare($this->queue, false, $this->durable, false, false);
```

Príznaky sú passive `false`, durable `$durable` (predvolene `true`), exclusive `false`, auto-delete `false`. Od 6.0 fronta prežije reštart brokera ([issue #70](https://github.com/tomaj/hermes/issues/70)).

`send()` vytvorí `AMQPMessage` s `$amqpMessageProperties` a publikuje ju do predvoleného exchange s názvom fronty ako routing key:

```php
$this->getChannel()->basic_publish($rabbitMessage, '', $this->queue);
```

`$priority` sa prijme, ale nepoužije. `setupPriorityQueue()` vždy vyhodí `NotSupportedException`.

S `$durable = true` driver publikuje aj perzistentné správy (`delivery_mode` 2), ak si `delivery_mode` nenastavíte sami v `$amqpMessageProperties`.

### Prechod fronty z 5.x

Hermes 5.x deklaroval frontu ako non-durable. RabbitMQ odmietne znova deklarovať existujúcu frontu s inými príznakmi a zatvorí kanál s `PRECONDITION_FAILED`. Frontu deklaruje emitter aj worker: `emit()` vyhodí výnimku a worker zaloguje `Exiting hermes dispatcher - driver error: ...` na úrovni `error` a skončí s nenulovým kódom, pretože `Dispatcher::handle()` chyby drivera vyhadzuje znova. Máte dve možnosti:

- Zastavte producentov, nechajte workery vyprázdniť starú frontu, zmažte ju (`rabbitmqctl delete_queue --if-empty hermes_queue`) a nechajte 6.0 deklarovať ju ako durable. Proces s 5.x durable frontu použiť nemôže, takže návrat späť vyžaduje rovnaké kroky.
- Ponechajte starú frontu a vypnite durable:

```php
$driver = new LazyRabbitMqDriver($connection, 'hermes_queue', [], 0, 'hermes', durable: false);
```

S `durable: false` je fronta non-durable a správy nie sú označené ako perzistentné, rovnako ako v 5.x. Potvrdzovanie zostáva manuálne.

**RabbitMQ 4.3 a novší:** broker už predvolene nedovolí klientom vytvárať non-durable, non-exclusive fronty (zastaraná funkcia `transient_nonexcl_queues`; 4.3.6 ich odmieta, 4.0.5 ich ešte povolil s varovaním). Deklarácia zlyhá s `INTERNAL_ERROR` a broker zatvorí pripojenie. To rozbije emitter alebo workera s 5.x, ktorý deklaruje non-durable frontu, aj Hermes s `durable: false`. Non-durable fronty navyše pri reštarte brokera zmiznú, takže po prechode na 4.3 nezostane žiadna stará fronta na zdieľanie. Buď nasaďte 6.0 s predvolenou durable frontou, alebo funkciu v `rabbitmq.conf` znova povoľte, kým všetky procesy nebežia na 6.0 s durable frontami:

```ini
deprecated_features.permit.transient_nonexcl_queues = true
```

Nastavenie začne platiť po reštarte brokera a reštart zmaže non-durable fronty aj so správami: najprv ich vyprázdnite.

`executeAt` sa serializuje a potom ignoruje. Publikovanie prebehne hneď. [Opakovanie](/sk/guide/retry) na tomto driveri tiež publikuje hneď.

## Konzumácia

<Diagram name="drivers-rabbitmq-2" alt="Postup: worker deklaruje durable frontu, konzumuje s prefetch 1, každé telo odovzdá callbacku a potom ho potvrdí" />

`wait()` najprv nastaví `basic_qos` s prefetch count `1`, takže broker dá každému workerovi naraz jednu nepotvrdenú správu. Argumenty `basic_consume` sú fronta, `$consumerTag` (predvolene `hermes`), `no_local` false, `no_ack` false, exclusive false, nowait false a callback. Pre každé doručenie:

- Telo sa deserializuje a odovzdá callbacku dispatchera. Keď sa vráti, driver zavolá `basic_ack` a započíta správu do limitu správ.
- Ak callback vyhodí výnimku, driver zavolá `basic_nack` s vrátením do fronty, takže správa sa vráti do fronty, a výnimku vyhodí znova.
- Ak sa telo nedá deserializovať, driver zavolá `basic_nack` bez vrátenia do fronty, aby sa pokazená správa nedoručovala donekonečna. Ak si ju chcete ponechať, pridajte dead-letter exchange cez policy (`rabbitmqctl set_policy -p / hermes-dlx '^hermes$' '{"dead-letter-exchange":"hermes-dlx"}' --apply-to queues`, s názvom vašej fronty vo vzore; exchange `hermes-dlx` potrebuje naviazanú frontu a na frontu sa uplatní len policy s najvyššou prioritou). Nepoužívajte argumenty fronty: driver deklaruje frontu bez argumentov, takže `x-dead-letter-exchange` alebo `x-queue-type` spôsobia zlyhanie deklarácie s `PRECONDITION_FAILED`.
- Ak worker zomrie pred potvrdením, RabbitMQ správu po zatvorení pripojenia doručí znova.

Doručenie je at-least-once, preto majú byť handlery idempotentné. Dispatcher výnimky handlerov zachytáva sám, takže handler, ktorý vyhodí akýkoľvek `Throwable` (`Exception` alebo PHP `Error`, napríklad `TypeError`), sa aj tak potvrdí ([#112](https://github.com/tomaj/hermes/issues/112)). Pre tie [Opakovanie](/sk/guide/retry) publikuje novú kópiu.

Slučka konzumácie volá `AMQPChannel::wait(null, false, $timeout)`, ktoré blokuje, kým nepríde doručenie alebo neuplynie timeout. Doručenie sa spracuje hneď, takže timeout nespomalí zaťaženú frontu. Po timeoute driver skontroluje ukončenie a čaká znova, takže nečinný worker nespotrebúva CPU. `$refreshInterval` je tento timeout v sekundách; `0`, predvolená hodnota, znamená jednu sekundu. Príklad odovzdáva `0`. S dlhším timeoutom si nečinný worker [ukončenie](/sk/guide/shutdown) všimne neskôr:

```php
new LazyRabbitMqDriver($connection, 'hermes_queue', [], 5, 'hermes-worker-1');
```

Pred 6.0 slučka pollovala bez blokovania a po každom čakaní spala `$refreshInterval` sekúnd, takže `0` na nečinnej fronte vyťažilo jedno jadro CPU a každá iná hodnota zdržala každú správu.

`$priorities` vo `wait()` sa nepoužíva.

Po dosiahnutí limitu správ sa prerušia obe slučky a driver potom zatvorí kanál aj pripojenie. Ukončenie vyhodí `ShutdownException` ešte pred zatvorením. `Dispatcher::handle()` ju zachytí, zaloguje `Exiting hermes dispatcher - shutdown` a vráti sa.

Tento driver má `MaxItemsTrait`, `ShutdownTrait` a `SerializerAwareTrait`. `SharedFileShutdown` RabbitMQ nepotrebuje. `RedisShutdown` stále potrebuje klienta Redis, ktorý tento driver nemá.

## Časté otázky

### Sú správy perzistentné?

Áno, od 6.0. Fronta je durable a správy sa publikujú s `delivery_mode` 2. Ak chcete ponechať non-durable frontu z 5.x na brokeri, ktorý non-durable fronty ešte povoľuje, odovzdajte `durable: false` (RabbitMQ 4.3 ich predvolene nepovoľuje, pozrite [Prechod fronty z 5.x](#prechod-fronty-z-5-x)). Vo verzii 5.x bola fronta non-durable ([issue #70](https://github.com/tomaj/hermes/issues/70)).

### Potvrdzuje driver správy?

Áno, od 6.0. Po dokončení handlera pošle `basic_ack` a keď callback zlyhá, `basic_nack`. Verzia 5.x konzumovala s `no_ack = true`.

### Čakajú opakovania?

Nie. `executeAt` sa ignoruje, takže kópia na opakovanie sa publikuje hneď.

### Môžem používať priority?

Nie. `setupPriorityQueue()` vyhodí `NotSupportedException`. Namiesto toho použite samostatné fronty a workery.

### Prečo môj worker používa 100 % CPU?

Na 6.0 by nemal: nečinný worker blokuje v čakaní na kanáli. Pred 6.0 predvolený interval obnovy `0` spôsoboval, že slučka konzumácie pollovala bez prestávky; tam ako štvrtý argument konštruktora odovzdajte `1`.
