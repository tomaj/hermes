---
title: "Logovanie"
description: "PSR-3 logovanie v Emitteri a Dispatcheri, presné riadky, ktoré Hermes zapisuje, loggery v handleroch a Tracy."
---

# Logovanie

`Emitter` aj `Dispatcher` prijmú ľubovoľný `Psr\Log\LoggerInterface`. Bez loggera obe triedy každé logovanie preskočia. Hermes za vás Monolog nekonfiguruje. `monolog/monolog` je odporúčaný balík.

<Diagram name="logging-1" alt="Ktorý komponent zapisuje ktorý riadok logu: emit, začiatok, koniec, chyba a oznámenie o ukončení" />

```php
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Tomaj\Hermes\Dispatcher;

$log = new Logger('hermes');
$log->pushHandler(new StreamHandler('hermes.log'));

$dispatcher = new Dispatcher($driver, $log, $shutdown);
$emitter = new Emitter($driver, $log);
```

Ak emitter a dispatcher bežia v rôznych procesoch, dajte každému vlastnú inštanciu loggera. Objekt loggera sa cez frontu nezdieľa.

## Riadky, ktoré knižnica zapisuje

Úrovne sú PSR-3 reťazce (`Psr\Log\LogLevel`).

| Kde | Úroveň | Správa |
| --- | --- | --- |
| `Emitter::emit` | `info` | `Emitter send message #<id> to driver <class>` |
| `Dispatcher::emit` (zastarané) | `info` | `Dispatcher send message #<id> with priority <n> to driver <class>` |
| `Dispatcher::emit` (zastarané), prvé volanie na každej inštancii | `warning` | `Dispatcher::emit() is deprecated since 2.0 and removed in 7.0. Use Tomaj\Hermes\Emitter::emit().` |
| `Dispatcher::handle` | `info` | `Start handle message #<id> (<type>) priority:<n>` |
| `Dispatcher` po návrate handlera | `info` | `End handle message #<id> (<type>)` |
| `Dispatcher`, keď handler vyhodí výnimku | `error` | `Handler <class> throws exception - <exception message>` |
| `Dispatcher`, keď sa handler bez `RetryableHandlerInterface` opakuje podľa pravidla z 5.x (zastarané), raz za triedu | `warning` | `Handler <class> defines canRetry() and maxRetry() without implementing …RetryableHandlerInterface. …` |
| `Dispatcher`, keď `send()` kópie na opakovanie vyhodí výnimku, pred jej vyhodením ako chyby drivera | `error` | `Retry of message #<id> (<type>) lost - driver send failed: <class> - <exception message>` |
| `Dispatcher` po `SIGTERM` / `SIGINT` (ext-pcntl) | `notice` | `Exiting hermes dispatcher - received SIGTERM` (alebo `SIGINT`) |
| `Dispatcher` pri `ShutdownException` | `notice` | `Exiting hermes dispatcher - shutdown` |
| `Dispatcher`, keď driver vyhodí výnimku, pred jej opätovným vyhodením | `error` | `Exiting hermes dispatcher - driver error: <class> - <exception message>` |

Kontext správy emittera aj dispatchera tvoria `id`, `created`, `type`, `payload`, `retries` a `execute_at` (pred 6.0 emitter `retries` a `execute_at` vynechával). Kontext chyby handlera tvoria `error`, `message` (pole vyššie) a `exception`. `error` a `exception` obsahujú tú istú výnimku: `exception` je kľúč podľa PSR-3, `error` ostáva pre log procesory, ktoré ho už čítajú. Kontext chyby drivera je len `exception`.

::: warning Payload v logoch
Každý `info` riadok vyššie nesie celý `payload`, takže pri loggeri na úrovni `info` skončí payload každej správy v logoch. Ak payloady obsahujú osobné údaje, tokeny alebo veľké dáta, zvýšte úroveň loggera pre Hermes na `notice` alebo pridajte procesor, ktorý `context.payload` odstráni alebo zamaskuje.
:::

Typ bez zaregistrovaného handlera zaloguje začiatočný riadok. Koncový nie, pretože `handleMessage()` sa vôbec nezavolá.

## Handlery

Pred `handle()` dispatcher zavolá `setLogger()`, keď logger nie je null a metóda existuje. Bežný spôsob, ako túto metódu získať, je implementovať `LoggerAwareInterface`. Samotná kontrola je `method_exists($handler, 'setLogger')`.

```php
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class SendEmailHandler implements HandlerInterface
{
    use LoggerAwareTrait;

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        if ($payload === null) {
            return false;
        }

        if ($this->logger instanceof LoggerInterface) {
            $this->logger->info('Trying to send email to ' . $payload['to']);
        }

        mail($payload['to'], $payload['subject'], $payload['message']);

        return true;
    }
}
```

`LoggerAwareTrait` nechá `$this->logger` nenastavený, kým ho dispatcher nevloží. Kontrola `instanceof` zaistí, že handler funguje aj v teste, ktorý logger nikdy neodovzdá.

## Tracy

`tracy/tracy` je voliteľná (`suggest`); nainštalujete ju cez `composer require tracy/tracy`. Hermes do nej zapisuje, len keď je nainštalovaná a `Debugger::isEnabled()` už vracia true:

- výnimku zachytenú v `handleMessage()`
- akúkoľvek inú `Exception`, ktorá unikne z `Driver::wait()`, okrem `ShutdownException`; tá sa potom vyhodí znova

Oba prípady používajú `Debugger::log($exception, Debugger::EXCEPTION)`. Ak tieto súbory chcete, zavolajte `Debugger::enable()` pri štarte workera. Hermes Tracy sám nezapne.

## Časté otázky

### Loguje Hermes niečo predvolene?

Nie. Odovzdajte PSR-3 logger `Emitter`u a `Dispatcher`u. Bez neho sa každé logovanie preskočí.

### Aký logger použiť?

Ľubovoľný PSR-3 logger. Bežná voľba je Monolog, ktorý je v `suggest`.

### Ako handler získa logger?

Implementujte `setLogger()`, napríklad cez `LoggerAwareTrait`. Dispatcher mu svoj logger vloží pred zavolaním `handle()`.

### Kam idú výnimky z handlerov?

Zalogujú sa na úrovni `error` s výnimkou v kontexte a odovzdajú sa Tracy, keď je Tracy nainštalovaná a `Debugger::isEnabled()` vráti true.

### Potrebujem Tracy?

Nie. Je len odporúčaná. Bez nej Hermes loguje len cez PSR-3 logger.

### Prečo emitter loguje slovo Dispatcher?

Od 6.0 už nie: `Emitter::emit()` loguje `Emitter send message #<id> to driver <class>`. Verzia 5.x písala `Dispatcher send message …`, takže upravte vyhľadávania v logoch a alerty, ktoré hľadajú starý text. Ponecháva ho len zastarané `Dispatcher::emit()`.
