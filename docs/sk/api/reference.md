---
title: "Referencia API"
description: "Každá verejná trieda, rozhranie, trait a výnimka v Hermese 6.x s jej signatúrou."
---

# Referencia API

Verejné typy v `src/` pre 6.x. Cesty sú relatívne ku koreňu balíka. Celé skripty nájdete v príkladoch na iných miestach tejto príručky.

<Diagram name="api-reference-1" alt="Message implementuje MessageInterface, Emitter posiela cez DriverInterface a Dispatcher volá HandlerInterface" />

## Tomaj\Hermes\Message

`src/Message.php`. Implementuje `MessageInterface`.

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

| Metóda | Vracia |
| --- | --- |
| `getId()` | `string`. UUID v4, keď bolo id v konštruktore `null` (predvolené) alebo `''`; akýkoľvek iný reťazec, vrátane `"0"`, sa vráti tak, ako bol zadaný. |
| `getCreated()` | `float`. `microtime(true)`, ak nie je zadaný. |
| `getExecuteAt()` | `float\|null` |
| `getType()` | `string` |
| `getPayload()` | `array\|null` |
| `getRetries()` | `int` |

Neexistuje žiadny setter ani vlastnosť priority.

## Tomaj\Hermes\MessageInterface

`src/MessageInterface.php`. Šesť getterov vyššie.

## Tomaj\Hermes\Emitter

`src/Emitter.php`. Implementuje `EmitterInterface`.

```php
public function __construct(DriverInterface $driver, ?LoggerInterface $logger = null)

public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
```

Volá `Driver::send`. Ak je nastavený `$logger`, loguje na úrovni `info`. Vracia `$this`. Vyhodí `UnknownPriorityException`, keď ju vyhodí driver.

## Tomaj\Hermes\EmitterInterface

`src/EmitterInterface.php`. Metóda `emit()` vyššie.

## Tomaj\Hermes\BufferedEmitter

`src/BufferedEmitter.php`. Od 6.0. Implementuje `EmitterInterface` a `Countable`; dekoruje iný `EmitterInterface`.

```php
public function __construct(EmitterInterface $emitter, ?callable $shouldBuffer = null)

public function emit(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): EmitterInterface
public function flush(): void
public function discard(): int
public function count(): int
```

`emit()` správu podrží, keď je `$shouldBuffer` `null` alebo pre `($message, $priority)` vráti `true` (callable bez parametrov sa volá bez argumentov); inak hneď zavolá vnútorný `emit()`. Vracia `$this`. `flush()` odošle podržané správy v poradí s ich prioritami; keď vnútorný emitter vyhodí výnimku, odoslané zostanú odoslané, zvyšok zostane podržaný a výnimka sa vyhodí znova. `flush()` zavolaný počas `flush()` hneď skončí. `discard()` zahodí podržané správy a vráti ich počet. Pozrite [Emitovanie po commite do databázy](/sk/guide/buffered-emitter).

## Tomaj\Hermes\Dispatcher

`src/Dispatcher.php`. Implementuje `DispatcherInterface`.

```php
public const int DEFAULT_PRIORITY = 100;

public function __construct(
    DriverInterface $driver,
    ?LoggerInterface $logger = null,
    ?ShutdownInterface $shutdown = null,
    ?Randomizer $randomizer = null
)
```

Keď `$shutdown` nie je `null` a driver má `setShutdown()`, konštruktor ju zavolá. `$randomizer` (`Random\Randomizer`) dodáva náhodnú zložku opakovania; predvolene je to `new Randomizer()`. Pozrite [Opakovanie](/sk/guide/retry#opakovatelne-casy-opakovania).

| Metóda | Správanie |
| --- | --- |
| `emit(MessageInterface $message, int $priority = self::DEFAULT_PRIORITY): DispatcherInterface` | Zastarané. Posiela cez driver a loguje. Uprednostnite `Emitter`. |
| `handle(array $priorities = []): void` | Blokuje v `Driver::wait`. Zachytí `ShutdownException` a zaloguje notice. Zachytí ostatné `Exception`, zaloguje ich na úrovni `error`, zapíše ich do Tracy, keď je Tracy nainštalovaná a zapnutá, a znova ich vyhodí. |
| `registerHandler(string $type, HandlerInterface $handler): DispatcherInterface` | Pridá jeden handler pre `$type`. |
| `registerHandlers(string $type, array $handlers): DispatcherInterface` | Zavolá `registerHandler` pre každý. |
| `unregisterHandler(string $type, HandlerInterface $handler): DispatcherInterface` | Odstráni danú inštanciu pre daný typ. |
| `unregisterAllHandlers(): DispatcherInterface` | Vyprázdni mapu. |

Pole pre `handle()` je zoznam celočíselných priorít. Prázdne znamená všetky fronty, ktoré driver má.

Výnimky handlerov sa zachytávajú pre každý handler zvlášť. Z `handle()` neuniknú, pokiaľ nevyhodí výnimku samotný driver. Opakovanie opisuje [Opakovanie](/sk/guide/retry). Súkromný vzorec oneskorenia je `microtime(true) + $retries ** 4 + 15 + $randomizer->getInt(1, 30) * ($retries + 1)`.

## Tomaj\Hermes\DispatcherInterface

`src/DispatcherInterface.php`. `registerHandler`, `registerHandlers`, `unregisterAllHandlers`, `unregisterHandler`, `handle`. `emit()` je len na konkrétnej triede.

## Tomaj\Hermes\Driver\DriverInterface

`src/Driver/DriverInterface.php`.

```php
public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool;

public function setupPriorityQueue(string $name, int $priority): void;

public function wait(Closure $callback, array $priorities): void;
```

`send()` môže vyhodiť `SerializeException` alebo `UnknownPriorityException`. `setupPriorityQueue()` môže vyhodiť `NotSupportedException`. `wait()` môže vyhodiť `UnknownPriorityException`, `SerializeException` (telo, ktoré nejde deserializovať) alebo `ShutdownException`.

## Drivery

| Trieda | Súbor | Konštruktor |
| --- | --- | --- |
| `RedisSetDriver` | `src/Driver/RedisSetDriver.php` | `(Redis $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')` |
| `PredisSetDriver` | `src/Driver/PredisSetDriver.php` | `(Client $redis, string $key = 'hermes', int $refreshInterval = 1, string $scheduleKey = 'hermes_schedule')` |
| `LazyRabbitMqDriver` | `src/Driver/LazyRabbitMqDriver.php` | `(AbstractConnection $connection, string $queue, array $amqpMessageProperties = [], int $refreshInterval = 0, string $consumerTag = 'hermes', bool $durable = true)` |
| `AmazonSqsDriver` | `src/Driver/AmazonSqsDriver.php` | `(SqsClient $client, string $queueName, array $queueAttributes = [])` |
| `FileSystemDriver` | `src/Driver/FileSystemDriver.php` | `(string $directory, string $queue = 'hermes', int $refreshInterval = 1, int $visibilityTimeout = 300)` |
| `KafkaDriver` | `src/Driver/KafkaDriver.php` | `(Producer $producer, ?KafkaConsumer $consumer = null, string $topic = 'hermes', int $consumeTimeout = 1000, int $flushTimeout = 10000)` |

`RedisSetDriver::setupPriorityQueue`, `PredisSetDriver::setupPriorityQueue` a `FileSystemDriver::setupPriorityQueue` uložia `$name` pod `$priority`; `FileSystemDriver` vyhodí `InvalidArgumentException` pre názov, ktorý nie je bezpečný názov priečinka. `LazyRabbitMqDriver`, `AmazonSqsDriver` a `KafkaDriver` vždy vyhodia `NotSupportedException` a deklarujú návratový typ `never`.

`RedisSetDriver`, `PredisSetDriver` a `FileSystemDriver` vyhodia v konštruktore `InvalidArgumentException` pre záporné `$refreshInterval` a `FileSystemDriver` aj pre záporné `$visibilityTimeout`.

Všetkých šesť používa (`use`) `MaxItemsTrait`, `ShutdownTrait` a `SerializerAwareTrait`.

## Traity

`src/Driver/MaxItemsTrait.php`

| Metóda | Správanie |
| --- | --- |
| `setMaxProcessItems(int $count): void` | `0` znamená bez limitu. |
| `incrementProcessedItems(): int` | Zvýši a vráti počet. |
| `processed(): int` | Aktuálny počet, začína na 0. |
| `shouldProcessNext(): bool` | `false`, keď `processed >= max` a max nie je 0. |

`src/Driver/ShutdownTrait.php`

| Metóda | Správanie |
| --- | --- |
| `setShutdown(ShutdownInterface $shutdown): void` | Uloží ho a nový `DateTimeImmutable` ako začiatok. |

`shouldShutdown()` a `checkShutdown()` sú súkromné. `checkShutdown()` vyhodí `ShutdownException` s prázdnou správou.

`src/Driver/SerializerAwareTrait.php`

| Metóda | Správanie |
| --- | --- |
| `setSerializer(SerializerInterface $serializer): void` | Nahradí serializér. |

## Výnimky

| Trieda | Rozširuje | Kedy |
| --- | --- | --- |
| `HermesException` | `\Exception` | Základný typ. |
| `SerializeException` | `HermesException` | Zlyhalo kódovanie alebo dekódovanie JSON. Správa nikdy neobsahuje serializovaný reťazec, len dôvod, jeho dĺžku a prefix SHA-256. |
| `Shutdown\ShutdownException` | `HermesException` | Kontrola ukončenia požiadala workera o zastavenie. |
| `Driver\UnknownPriorityException` | `HermesException` | `send()` alebo `wait()` použili číslo, ktoré `setupPriorityQueue()` nikdy neuložila. Správa: `Unknown priority <n>`. |
| `Driver\NotSupportedException` | `HermesException` | Prioritné fronty na RabbitMQ, SQS alebo Kafka. |
| `Driver\FileSystemDriverException` | `HermesException` | `FileSystemDriver` nemôže vytvoriť priečinok, alebo zapísať, presunúť či prečítať súbor správy. |
| `Driver\KafkaDriverException` | `HermesException` | Kafka nepotvrdila odoslanú správu, consumer nahlásil chybu, alebo `wait()` nemá consumer. |

## Tomaj\Hermes\SerializerInterface

`src/SerializerInterface.php`.

```php
public function serialize(MessageInterface $message): string;

public function unserialize(string $string): MessageInterface;
```

Obe môžu vyhodiť `SerializeException`.

## Tomaj\Hermes\MessageSerializer

`src/MessageSerializer.php`. Predvolený serializér, ktorý vytvára každý vstavaný driver.

`serialize()` zakóduje do JSON `message.id`, `message.type`, `message.created`, `message.payload`, `message.execute_at` a `message.retries`, s `JSON_INVALID_UTF8_IGNORE`.

`unserialize()` vyžaduje pole s objektom `message`, ktorý má `type`, `id` a `created`. `execute_at` sa použije, keď je číselné. `retries` sa použije, keď je číselné, inak `0`. `payload` sa použije, keď je pole, inak `null`. Návratová hodnota je `Message`. Inak vyhodí `SerializeException` s dôvodom, dĺžkou a `substr(hash('sha256', $string), 0, 16)`: `Cannot unserialize message: invalid JSON (<error>)`, `Cannot unserialize message: no "message" key`, `Invalid message format: "message" needs type, id and created` alebo `Invalid message format: type and id must be strings and created a number`. Pri neplatnom JSON zostane `JsonException` ako `previous`.

## Tomaj\Hermes\Handler\HandlerInterface

`src/Handler/HandlerInterface.php`.

```php
public function handle(MessageInterface $message): bool;
```

Docblock povoľuje, aby metóda vyhodila `\Exception`.

## Tomaj\Hermes\Handler\RetryableHandlerInterface

`src/Handler/RetryableHandlerInterface.php`. Rozširuje `HandlerInterface`.

| Metóda | Význam |
| --- | --- |
| `canRetry(MessageInterface $message, Throwable $exception): bool` | Volá sa po tom, čo `handle()` vyhodila výnimku, kým `getRetries() < maxRetry()`. `false` opakovanie preskočí. |
| `maxRetry(): int` | Najväčší počet opakovaní správy. `0` opakovanie vypne. |

## Tomaj\Hermes\Handler\RetryTrait

`src/Handler/RetryTrait.php`. Implementuje metódy `RetryableHandlerInterface`; rozhranie deklaruje trieda. Handler, ktorý trait používa alebo obe metódy definuje sám bez deklarovania rozhrania, sa opakuje podľa zastaraného pravidla z 5.x: kým `getRetries() < maxRetry()`, bez volania `canRetry()`, s jedným `E_USER_DEPRECATED` a jedným `warning` na triedu. 7.0 toto pravidlo odstráni.

| Metóda | Vracia |
| --- | --- |
| `canRetry(MessageInterface $message, Throwable $exception): bool` | `true`. |
| `maxRetry(): int` | `25`. Nový pokus sa pošle, keď `getRetries() < maxRetry()`. |

## Tomaj\Hermes\Handler\EchoHandler

`src/Handler/EchoHandler.php`. Implementuje `RetryableHandlerInterface` a používa `RetryTrait`.

`handle()` vypíše `Received message: #<id> (type <type>)` a `Payload: <json>` a vráti `true`.

## Ukončenie

`src/Shutdown/ShutdownInterface.php`

```php
public function shouldShutdown(DateTimeInterface $startTime): bool;

public function shutdown(?DateTimeInterface $shutdownTime = null): bool;
```

| Trieda | Konštruktor | Signál |
| --- | --- | --- |
| `SharedFileShutdown` | `(string $filePath)` | Zapíše čas s mikrosekundami; funguje aj `touch`. Zastaví, keď je čas neskorší ako začiatok a nie je v budúcnosti. |
| `RedisShutdown` | `(Redis $redis, string $key = 'hermes_shutdown')` | Cez `SET` uloží čas s mikrosekundami. Zastaví, keď je hodnota nastavená, nie je v budúcnosti a je neskoršia ako začiatok. |
| `PredisShutdown` | `(Client $redis, string $key = 'hermes_shutdown')` | Rovnaké pravidlá. `shutdown()` je true, keď je odpoveď Predisu `OK`. |

Podrobnosti a rozdiely medzi pravidlami pre súbor a Redis sú na stránke [Ukončenie a limit správ](/sk/guide/shutdown).

## Časté otázky

### Kde je argument priority?

Na `Emitter::emit()` a `DriverInterface::send()`, ako druhý argument. `Message` prioritu nemá.

### Ktoré metódy môžu vyhodiť `UnknownPriorityException`?

`send()` a `wait()` na Redis driveroch, keď priorita nebola zaregistrovaná cez `setupPriorityQueue()`.

### Je `Dispatcher::emit()` stále podporovaná?

Funguje, ale je zastaraná. Používajte `Emitter`.

### Ktoré traity používajú vstavané drivery?

`MaxItemsTrait`, `ShutdownTrait` a `SerializerAwareTrait`, všetkých šesť.

### Kde sú úplné príklady použitia?

V sekcii [Príklady](/sk/examples/) a v `examples/` v repozitári.
