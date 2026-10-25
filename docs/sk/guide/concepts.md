---
title: "Pojmy"
description: "Message, Emitter, Dispatcher, Handler a Driver: päť typov Hermesu a ako medzi nimi putuje správa."
---

# Pojmy

Prácu robí päť typov. Všetko ostatné je driver, signál na ukončenie alebo serializér.

<Diagram name="concepts-1" alt="Webová požiadavka vytvorí Message, Emitter ju odovzdá Driveru a fronte a Dispatcher ju podá Handleru" />

Správa nie je riadok so stĺpcom stavu. Tieto názvy sú fázy, ktorými kód naozaj prechádza. `Processing` začne až potom, čo driver payload z brokera odstránil.

<Diagram name="concepts-2" alt="Stavy správy: vložená, naplánovaná alebo vo fronte, spracúva sa, potom hotová, neúspešná alebo zopakovaná ako kópia" />

## Message

`Tomaj\Hermes\Message` je vstavaná implementácia `MessageInterface`. Argumenty konštruktora sú typ, payload, id, čas vytvorenia, čas vykonania a počet opakovaní:

```php
public function __construct(
    string $type,
    ?array $payload = null,
    ?string $messageId = null,
    ?float $created = null,
    ?float $executeAt = null,
    int $retries = 0
)
```

Priorita medzi nimi nie je. Id `null` (predvolené) aj prázdny reťazec `''` sa zmenia na UUID v4 z `ramsey/uuid`. Akýkoľvek iný reťazec, vrátane `"0"`, sa ponechá ako id. `created` je predvolene `microtime(true)`. `executeAt` zostane `null`, ak neodovzdáte časovú značku. `retries` začína na `0`.

`getPayload()` vracia `array|null`. Payload musí prežiť JSON. Nevkladajte doň resources, sockety ani živé pripojenie k databáze. `MessageSerializer` ukladá tento tvar:

```json
{
  "message": {
    "id": "6f1c0a2e-9b44-4d1a-8c33-0a9e5d77b214",
    "type": "send-email",
    "created": 1710000000.123,
    "payload": {"to": "test@test.com"},
    "execute_at": null,
    "retries": 0
  }
}
```

Kódovanie používa `JSON_INVALID_UTF8_IGNORE`. Chyba JSON v `json_encode()` vyhodí `SerializeException`. Dekódovanie vyžaduje `message.type`, `message.id` a `message.created`. Payload, ktorý existuje, ale nie je pole, sa zmení na `null`. Reťazec, ktorý sa nedá dekódovať, vyhodí `SerializeException`; jej text uvádza dôvod, dĺžku a prefix SHA-256, nikdy nie samotný reťazec.

Správa si nepamätá, s akou prioritou bola poslaná. Priorita existuje len ako argument `send()` / `emit()`.

## Emitter

`Emitter` volá aplikačný kód.

```php
public function __construct(DriverInterface $driver, ?LoggerInterface $logger = null)

public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
```

`emit()` zavolá `$driver->send($message, $priority)` a ak ste odovzdali logger, zaloguje na úrovni `info`:

```text
Emitter send message #<id> to driver <driver class>
```

Pole kontextu obsahuje `id`, `created`, `type`, `payload`, `retries` a `execute_at`, vrátane celého payloadu (pozri [Logovanie](/sk/guide/logging)). Hermes 5.x tu písal `Dispatcher send message …`. Metóda vracia `$this`.

`UnknownPriorityException` vyhodí driver, keď `$priority` nikdy nebola zaregistrovaná cez `setupPriorityQueue()`.

Ak chcete emitovať až po commite databázovej transakcie, obaľte emitter do `BufferedEmitter`. Pozrite [Emitovanie po commite do databázy](/sk/guide/buffered-emitter).

## Dispatcher

`Dispatcher` je worker.

```php
public function __construct(
    DriverInterface $driver,
    ?LoggerInterface $logger = null,
    ?ShutdownInterface $shutdown = null
)

public function handle(array $priorities = []): void
```

`DEFAULT_PRIORITY` je `100`. Konštanta `PRIORITY_HIGH` neexistuje. Zakomentované volanie v `examples/redis/processor.php` odkazuje na konštantu, ktorú trieda nemá.

`handle()` volá `Driver::wait()`. Closure dostane správu a prioritu, ktorú driver našiel (ak ju driver neuvedie, použije sa `100`). Pre každú správu dispatcher:

1. Zaloguje `Start handle message #<id> (<type>) priority:<n>` na úrovni `info`.
2. Spustí každý handler zaregistrovaný pre `$message->getType()` v poradí registrácie.
3. Ak dostal objekt na ukončenie a `shouldShutdown($startTime)` vráti true, vyhodí `ShutdownException`.
4. Vráti výsledok handlera driveru. Žiadny zo vstavaných driverov túto hodnotu nečíta.

Keď pre typ nie je zaregistrovaný žiadny handler, `dispatch()` vráti `true` a nezaloguje `End handle message`. Správa už vo fronte nie je.

`Dispatcher::emit()` stále posiela cez driver, ale metóda je zastaraná. Použite `Emitter`.

Handlery meníte cez `registerHandler()`, `registerHandlers()`, `unregisterHandler()` a `unregisterAllHandlers()`. `unregisterHandler()` odstráni jednu inštanciu objektu (`!==`) pre jeden typ. `unregisterAllHandlers()` vymaže všetky typy. Obe existujú preto, aby testy mohli znova použiť dispatcher. Pribudli vo verzii 4.2.0.

## Handler

```php
public function handle(MessageInterface $message): bool;
```

Handler robí pomalú prácu: e-maily, HTTP, súbory. Hermes ho volá len z `Dispatcher`a, ktorý spúšťate z CLI.

<Diagram name="concepts-3" alt="Čo znamená výsledok handlera: true je úspech, false je zlyhanie bez opakovania, výnimka môže poslať kópiu na opakovanie" />

| Výsledok | Čo Hermes urobí |
| --- | --- |
| `true` | Zaloguje koncový riadok. Celkový výsledok zostane `true`, ak všetky handlery vrátili `true`. |
| `false` | Celkový výsledok bude `false`. Nič sa neopakuje. |
| Výnimka alebo `Error` (akýkoľvek `Throwable`) | Zaloguje na úrovni `error`, zapíše do Tracy, ak je Tracy zapnutá, a prípadne [zopakuje](/sk/guide/retry). Tento handler vráti slučke `false`. Ďalšie handlery pre ten istý typ sa aj tak spustia. |

Ak má dispatcher logger a handler má `setLogger()` (napríklad `Psr\Log\LoggerAwareTrait`), logger sa mu nastaví pred `handle()`.

`EchoHandler` je vstavaný handler, ktorý používajú príklady. Vypíše správu a vráti `true`. Implementuje `RetryableHandlerInterface` s `RetryTrait`. Samotné `EchoHandler::handle()` výnimku nevyhadzuje. Od 6.0 je trieda `final`; namiesto rozširovania si napíšte vlastný handler.

## Driver

```php
public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool;

public function setupPriorityQueue(string $name, int $priority): void;

public function wait(Closure $callback, array $priorities): void;
```

`send()` musí skončiť rýchlo. Beží vo webovom procese. `wait()` blokuje. Beží vo workeri. Zoznam `$priorities` je filter **celočíselných priorít**. Prázdne pole znamená „každá fronta, ktorú driver pozná“. Neprázdne pole preskočí priority, ktoré v ňom nie sú. RabbitMQ, SQS a Kafka argument prijmú, ale nepoužijú.

`setupPriorityQueue()` na `LazyRabbitMqDriver` vyhodí `NotSupportedException` s textom `LazyRabbitMqDriver is not supporting priority queues now`. `AmazonSqsDriver` vyhodí `AmazonSQS is not supporting priority queues now`.

Vstavané drivery používajú aj tri traity:

- `SerializerAwareTrait::setSerializer()` nahradí `MessageSerializer`.
- `MaxItemsTrait::setMaxProcessItems()` zastaví `wait()` po N správach. `0` znamená bez limitu.
- `ShutdownTrait::setShutdown()` umožní `wait()` vyhodiť `ShutdownException` medzi správami.

`Dispatcher` zavolá na driveri `setShutdown()`, keď driver túto metódu má a vy ste dispatcheru odovzdali objekt na ukončenie.

## Časté otázky

### Aký je rozdiel medzi `Emitter` a `Dispatcher`?

`Emitter` beží vo webovom procese a len posiela správy driveru. `Dispatcher` beží vo workeri: čaká na driveri a volá handlery zaregistrované pre typ každej správy.

### Pamätá si správa svoju prioritu?

Nie. Priorita je len druhý argument `emit()` a `send()`. Serializovaná správa nemá pole priority, takže opakovania a odložené správy sa vracajú s predvolenou prioritou 100.

### Čo môžem vložiť do payloadu?

Čokoľvek, čo prežije `json_encode()`: skaláry a polia. Nevkladajte objekty, resources ani pripojenia. Posielajte id a čerstvé dáta nech si načíta handler.

### Čo sa stane, keď pre typ nie je zaregistrovaný handler?

`dispatch()` vráti `true` a správa zmizne. Začiatočný riadok sa zaloguje, koncový nie. Handlery zaregistrujte na každom workeri, ktorý číta danú frontu.

### Môže pre jeden typ bežať viac handlerov?

Áno. Zavolajte `registerHandler()` viackrát alebo použite `registerHandlers()`. Spúšťajú sa v poradí registrácie a výnimka v jednom nezastaví ostatné.
