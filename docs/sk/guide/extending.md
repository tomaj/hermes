---
title: "Rozširovanie Hermesu"
description: "Napíšte vlastný driver, handler, serializér alebo signál na ukončenie pre rozhrania Hermesu 6.x."
---

# Rozširovanie Hermesu

Podporované body rozšírenia sú `DriverInterface`, `HandlerInterface`, `SerializerInterface` a `ShutdownInterface`. Od 6.0 je každá konkrétna trieda v balíku `final` (otvorená zostáva len `HermesException` ako základná výnimka), takže Hermes rozširujete implementáciou týchto rozhraní alebo obalením vstavanej triedy, nie dedením. Obaľujúci driver musí preposielať `setShutdown()`, pretože `Dispatcher` odovzdá objekt na ukončenie len driverom, ktoré túto metódu majú. Svoje implementácie označte `#[\Override]`, aby PHP nahlásilo metódu, ktorá už nezodpovedá rozhraniu.

<Diagram name="extending-1" alt="Vlastné triedy implementujúce DriverInterface, SerializerInterface, ShutdownInterface a HandlerInterface" />

`MemoryDriver` v tomto diagrame je príklad, ktorý by ste napísali vy. V balíku taká trieda nie je. `MessageSerializer`, `SharedFileShutdown` a `HandlerInterface` v ňom sú.

## Driver

`send()` musí byť bezpečné volať z webovej požiadavky. `wait()` smie blokovať. Pre prioritu, ktorú ste nezaregistrovali, vyhoďte `UnknownPriorityException`. Ak máte len jednu frontu, vyhoďte v `setupPriorityQueue()` `NotSupportedException`, ako to robia RabbitMQ, SQS a Kafka.

Tento driver zoraďuje priority cez `krsort`, takže väčšie číslo sa vyberie skôr. Vstavané Redis drivery robia od 6.0 to isté ([issue #50](https://github.com/tomaj/hermes/issues/50)).

```php
<?php

declare(strict_types=1);

use Closure;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\DriverInterface;
use Tomaj\Hermes\Driver\SerializerAwareTrait;
use Tomaj\Hermes\Driver\UnknownPriorityException;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\MessageSerializer;

class MemoryDriver implements DriverInterface
{
    use SerializerAwareTrait;

    /** @var array<int, string> */
    private $queues = [];

    /** @var array<int, string[]> */
    private $items = [];

    public function __construct()
    {
        $this->serializer = new MessageSerializer();
        $this->setupPriorityQueue('default', Dispatcher::DEFAULT_PRIORITY);
    }

    public function setupPriorityQueue(string $name, int $priority): void
    {
        $this->queues[$priority] = $name;
        if (!isset($this->items[$priority])) {
            $this->items[$priority] = [];
        }
    }

    public function send(MessageInterface $message, int $priority = Dispatcher::DEFAULT_PRIORITY): bool
    {
        if (!isset($this->items[$priority])) {
            throw new UnknownPriorityException('Unknown priority ' . $priority);
        }

        $this->items[$priority][] = $this->serializer->serialize($message);

        return true;
    }

    public function wait(Closure $callback, array $priorities = []): void
    {
        $queues = $this->queues;
        krsort($queues, SORT_NUMERIC);

        foreach ($queues as $priority => $name) {
            if ($priorities !== [] && !in_array($priority, $priorities, true)) {
                continue;
            }

            foreach ($this->items[$priority] as $serialized) {
                $callback($this->serializer->unserialize($serialized), $priority);
            }
        }
    }
}
```

Volajte `$callback($message, $priority)`. Dispatcher toto číslo zaloguje. Ak ho vynecháte, parameter closure má predvolenú hodnotu `Dispatcher::DEFAULT_PRIORITY`.

`SerializerAwareTrait` vám dá `setSerializer()`. Vstavané drivery ho používajú a `MessageSerializer` si vytvárajú samy. Urobte to isté, ak má váš driver prijať náhradu.

`MaxItemsTrait` a `ShutdownTrait` sú voliteľné, ale ak `wait()` beží v slučke, použite ich. `Dispatcher` zavolá `setShutdown()`, len keď metóda existuje.

V každej iterácii slučky, aj keď je fronta prázdna, volajte `$this->checkShutdown()` zo `ShutdownTrait` a blokujúce volania držte krátke (približne sekundu). Od 6.0 sa `SIGTERM` a `SIGINT` doručia len pri týchto kontrolách ([ukončenie](/sk/guide/shutdown#sigterm-a-sigint)). Driver, ktorý čaká bez volania `checkShutdown()`, signál počas nečinnosti ignoruje: v 5.x ho signál ukončil hneď, v 6.0 čaká, kým správca procesov nepošle `SIGKILL`.

Ak správu odstraňujete pred callbackom, zdokumentujte to, pretože Redis drivery to robia a volajúci predpokladajú, že pád doručenie stratí. `LazyRabbitMqDriver`, `AmazonSqsDriver`, `KafkaDriver` a `FileSystemDriver` ju odstránia až po tom, čo sa callback vráti bez výnimky. Ak ju odstránite až po vrátení `true`, ste prísnejší ako vstavané drivery. Dispatcher návratovú hodnotu na ceste späť stále ignoruje, takže váš driver si musí návratovú hodnotu callbacku prečítať sám. Vstavané drivery to nerobia.

So `ShutdownTrait` callback vždy vráti výsledok správy, aj keď počas nej prišla žiadosť o ukončenie, a váš driver skončí pri ďalšom `checkShutdown()`. Bez traitu vás dispatcher nemá ako inak zastaviť, takže po spracovaní správy vyhodí z callbacku `ShutdownException` ako v 5.x; výsledok tejto správy sa potom stratí. Ak váš driver výsledok číta, použite trait.

## Handler

Handler je jedna trieda pre typ úlohy alebo viac tried pre ten istý typ cez `registerHandler()` / `registerHandlers()`.

```php
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

class SendEmailHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if (!is_array($payload) || !isset($payload['to'], $payload['subject'], $payload['message'])) {
            return false;
        }

        mail($payload['to'], $payload['subject'], $payload['message']);

        return true;
    }

    public function maxRetry(): int
    {
        return 10;
    }
}
```

Ak má handler dostať logger dispatchera, pridajte `LoggerAwareTrait`. Pozrite [Logovanie](/sk/guide/logging).

## Serializér

`MessageSerializer` je predvolený a rozumejú mu ostatné drivery. Nahraďte ho len na oboch stranách, v emitteri aj vo workeri, inak si navzájom správy nedekódujú.

```php
use Tomaj\Hermes\Message;
use Tomaj\Hermes\MessageInterface;
use Tomaj\Hermes\SerializeException;
use Tomaj\Hermes\SerializerInterface;

class ExtraFieldSerializer implements SerializerInterface
{
    public function serialize(MessageInterface $message): string
    {
        $result = json_encode([
            'message' => [
                'id' => $message->getId(),
                'type' => $message->getType(),
                'created' => $message->getCreated(),
                'payload' => $message->getPayload(),
                'execute_at' => $message->getExecuteAt(),
                'retries' => $message->getRetries(),
            ],
        ]);

        if ($result === false) {
            throw new SerializeException('Cannot serialize message ' . $message->getId());
        }

        return $result;
    }

    public function unserialize(string $string): MessageInterface
    {
        $data = json_decode($string, true);
        if (!is_array($data) || !isset($data['message']) || !is_array($data['message'])) {
            throw new SerializeException('Cannot unserialize message');
        }

        $message = $data['message'];

        return new Message(
            $message['type'],
            isset($message['payload']) && is_array($message['payload']) ? $message['payload'] : null,
            $message['id'],
            (float) $message['created'],
            isset($message['execute_at']) && is_numeric($message['execute_at']) ? (float) $message['execute_at'] : null,
            isset($message['retries']) && is_numeric($message['retries']) ? (int) $message['retries'] : 0
        );
    }
}
```

```php
$driver->setSerializer(new ExtraFieldSerializer());
```

`setSerializer()` nie je v `DriverInterface`. Existuje na driveroch, ktoré používajú `SerializerAwareTrait`. Robia to všetkých šesť vstavaných driverov.

## Signál na ukončenie

`ShutdownInterface` implementujte, keď súbor ani kľúč v Redise nie je ten správny vypínač. `shouldShutdown(DateTimeInterface $startTime)` sa pravidelne volá; `Dispatcher` a drivery odovzdávajú `DateTimeImmutable`. `shutdown(?DateTimeInterface $shutdownTime = null)` je spôsob, akým iný proces požiada o zastavenie, s `DateTime` alebo `DateTimeImmutable`. Keď sa signál uložil, vráťte z `shutdown()` `true`.

Ak môžete, zachovajte pravidlo času spustenia. Workery, ktoré sa spustia po signáli, majú bežať ďalej, inak postupný reštart vypne aj nové procesy. `RedisShutdown` to robí. `SharedFileShutdown` to robí s časom v súbore, alebo s časom úpravy, keď bol súbor neskôr touchnutý. Vstavané spôsoby ukončenia porovnávajú s presnosťou na mikrosekundy.

## Časté otázky

### Čo môžem nahradiť?

Driver (`DriverInterface`), handlery (`HandlerInterface`), serializér (`SerializerInterface`) a signál na ukončenie (`ShutdownInterface`).

### Číta dispatcher výsledok handlera v mojom driveri?

Vráti ho callbacku vo vašom `wait()`. Vstavané drivery ho ignorujú. Vlastný driver ho môže použiť na potvrdenie len pri `true`.

### Musím pri výmene serializéra zmeniť obe strany?

Áno. Emitter aj worker musia používať ten istý serializér, inak si navzájom správy nedekódujú.

### Ako má vlastný signál na ukončenie zaobchádzať s novými workermi?

Ignorujte signály staršie ako čas spustenia workera, ako to robia vstavané, aby postupný reštart nezastavil nové procesy.

### Môžem v driveri zoradiť priority číselne?

Áno. Pred čítaním zoraďte mapu front cez `krsort`, ako v príklade na tejto stránke a vo vstavaných Redis a FileSystem driveroch.
