---
title: "Opakovanie"
description: "Ako RetryableHandlerInterface, RetryTrait a dispatcher opakujú neúspešný PHP handler, vzorec oneskorenia a čo s tým robí každý driver."
---

# Opakovanie

Opakovanie je nová `Message`, ktorú `Dispatcher` publikuje po tom, čo handler vyhodí výnimku. Nie je to nack brokera.

<Diagram name="retry-1" alt="Handler vyhodí výnimku, chyba sa zaloguje a ak zostávajú pokusy, odošle sa nová správa s neskorším executeAt" />

Handler sa opakuje, keď implementuje `RetryableHandlerInterface`. Keď `handle()` vyhodí výnimku, `Dispatcher` pošle kópiu na opakovanie, len ak má správa menej opakovaní ako `maxRetry()` a `canRetry($message, $exception)` vráti `true`. Po dosiahnutí `maxRetry()` sa `canRetry()` už nevolá ([#107](https://github.com/tomaj/hermes/issues/107)). Handler s `canRetry()` a `maxRetry()`, ale bez rozhrania, sa riadi [zastaraným pravidlom z 5.x](#handlery-bez-rozhrania-zastarane).

Vrátenie `false` z `handle()` opakovanie nespustí. Spustí ho len výnimka.

## RetryableHandlerInterface a RetryTrait

```php
use Tomaj\Hermes\Handler\RetryableHandlerInterface;
use Tomaj\Hermes\Handler\RetryTrait;
use Tomaj\Hermes\MessageInterface;

class FlakyHandler implements RetryableHandlerInterface
{
    use RetryTrait;

    public function handle(MessageInterface $message): bool
    {
        throw new RuntimeException('temporary failure');
    }

    public function maxRetry(): int
    {
        return 10;
    }
}
```

`RetryTrait` dodá obe metódy rozhrania (trait nemôže deklarovať `implements`, preto to robí trieda): ak ich neprepíšete, `canRetry()` vráti `true` a `maxRetry()` vráti `25`. `EchoHandler` trait používa. Jeho `handle()` vráti `true` a výnimku nevyhadzuje, takže trait nerobí nič, kým výnimka neunikne.

Pre chyby, ktoré neskôr uspieť nemôžu, opakovanie vypnite prepísaním `canRetry()`:

```php
use InvalidArgumentException;
use Throwable;

public function canRetry(MessageInterface $message, Throwable $exception): bool
{
    return !$exception instanceof InvalidArgumentException;
}
```

## Handlery bez rozhrania (zastarané) {#handlery-bez-rozhrania-zastarane}

Handler z 5.x má `canRetry()` a `maxRetry()`, vlastné alebo z `RetryTrait`, ale neimplementuje `RetryableHandlerInterface`. 6.0 ho opakuje presne ako 5.x: kým `getRetries() < maxRetry()`, a `canRetry()` sa **nevolá**, takže na jej výsledku ani parametroch nezáleží. Metódy môžu pochádzať z triedy, z rodičovskej triedy alebo z traitu. Handler, ktorý má len jednu z oboch metód, sa neopakuje.

Upgrade z 5.x tak funguje bez úpravy každého handlera, ale je to zastarané a 7.0 to odstráni. Keď takýto handler prvýkrát zlyhá, `Dispatcher` vyvolá `E_USER_DEPRECATED` a zaloguje `warning` s názvom triedy, raz za triedu a proces workera. Kontext `warning` má triedu pod kľúčom `handler`. Do tejto triedy pridajte `implements RetryableHandlerInterface` a vlastnej `canRetry()` dajte parametre `(MessageInterface $message, Throwable $exception)`. Odvtedy rozhoduje `canRetry()`, takže `false` opakovanie naozaj preskočí.

Nová správa si ponechá pôvodné id, typ, payload a `created`. `retries` bude `getRetries() + 1`. `executeAt` dostane hodnotu podľa vzorca nižšie. `send()` sa volá **bez** priority, takže Redis vloží kópiu do množiny naplánovaných správ a keď nastane čas, na kľúč predvolenej priority (`100`).

Hranicu pokrýva `HandleTest`. Správa, ktorá už má `retries = 5`, s handlerom, ktorého `maxRetry()` je `25`, sa publikuje znova s `retries = 6`. Tá istá správa s `maxRetry()` rovným `5` sa znova nepublikuje, pretože podmienka je `getRetries() < maxRetry()`. `RetryPolicyTest` pokrýva `canRetry()`, rozhranie, trait aj zastarané pravidlo z 5.x vrátane jedného upozornenia na triedu.

Kópia je jedno volanie `send()`. Na Redise a Predise toto `executeAt` odloží kópiu do zoradenej množiny; `FileSystemDriver` nechá súbor v `new/`, kým nebude splatný. Na RabbitMQ, SQS a Kafka sa publikuje hneď, takže číslo oneskorenia sa uloží, ale nečaká sa naň.

<Diagram name="retry-2" alt="Postup opakovania: dispatcher pošle kópiu s retries plus jedna, Redis ju naplánuje, RabbitMQ, SQS a Kafka ju publikujú hneď" />

## Oneskorenie

`Dispatcher::nextRetry()` obsahuje vzorec v štýle Sidekiqu:

```php
$retries = $message->getRetries();
return microtime(true) + $retries ** 4 + 15 + $this->randomizer->getInt(1, 30) * ($retries + 1);
```

`$retries` je počet **pred** zvýšením. Náhodná zložka pochádza z `Random\Randomizer`. Predvolene je to `new Randomizer()`, ktorý používa kryptograficky bezpečný engine. Do verzie 5.x to bol globálny `rand()`.

| Aktuálne `retries` | Pridané oneskorenie v sekundách |
| --- | --- |
| 0 | 15 + náhodné celé číslo od 1 do 30 |
| 1 | 16 + náhodné celé číslo od 2 do 60 |
| 2 | 31 + náhodné celé číslo od 3 do 90 |
| n | `n^4 + 15 + jitter(1, 30) * (n + 1)` |

Redis, Predis a súborový systém počkajú, pretože `executeAt` je v budúcnosti. RabbitMQ, SQS a Kafka publikujú kópiu hneď. Časová značka je stále v JSON a ďalšie zlyhanie stále zvýši `retries`, ale worker kópiu uvidí pri ďalšom čítaní. Na týchto driveroch sa zlyhávajúci handler môže točiť, kým nedosiahne `maxRetry()`.

### Opakovateľné časy opakovania

Ako štvrtý argument `Dispatcher`a odovzdajte vlastný `Randomizer`. S nastaveným seedom je postupnosť náhodných hodnôt pri každom behu rovnaká, čo sa hodí v testoch. Prvé opakovanie, ktoré worker vypočíta, dostane prvú hodnotu a tak ďalej. O ktorú správu ide, závisí od poradia doručenia a Redis drivery vyberajú náhodný prvok množiny:

```php
use Random\Engine\Mt19937;
use Random\Randomizer;

$dispatcher = new Dispatcher($driver, $logger, null, new Randomizer(new Mt19937(42)));
```

V produkcii ponechajte predvolený. Workery spustené s rovnakým seedom vypočítajú rovnaké oneskorenia, takže keď pri výpadku všetky naraz zlyhajú, ich opakovania sa na Redise a Predise vrátia spolu. RabbitMQ, SQS a Kafka `executeAt` ignorujú, takže náhodná zložka ich opakovania aj tak nerozloží. `mt_srand()` už náhodnú zložku neovplyvňuje, pretože nastavuje len globálny `rand()`, ktorý používala verzia 5.x.

Kontajner, ktorý `Dispatcher` autowiruje, vloží službu `Random\Randomizer`, ak ste ju zaregistrovali, napríklad so seedom určenú na niečo iné. Predvolené správanie zachováte odovzdaním `null`. V Symfony v `config/services.yaml`:

```yaml
services:
    Tomaj\Hermes\Dispatcher:
        arguments:
            $randomizer: null
```

V Laraveli v metóde `register()` service providera:

```php
$this->app->when(Dispatcher::class)->needs(Randomizer::class)->give(fn () => null);
```

V Nette v `config/services.neon`:

```yaml
services:
	- Tomaj\Hermes\Dispatcher(randomizer: null)
```

## Viac handlerov

Zavolá sa každý handler pre daný typ, aj keď predchádzajúci vyhodil výnimku. Výnimka sa zachytí v `handleMessage()`. Každý handler s opakovaním, ktorý vyhodí výnimku, môže publikovať vlastnú kópiu. Dva zlyhávajúce handlery vytvoria dve kópie.

## Čo nedostanete

- Žiadne opakovanie, keď je proces zabitý po tom, čo driver správu odstránil, a pred návratom `handle()`.
- Žiadne opakovanie po vrátení `false`.
- Žiadne opakovanie podľa priority. Kópia má predvolenú prioritu.
- Žiadnu dead-letter frontu v Hermese. Po poslednom povolenom zlyhaní sa metóda vráti a kópia sa nepošle.

## Časté otázky

### Ako zapnúť opakovanie?

Implementujte `RetryableHandlerInterface`, zvyčajne s `use RetryTrait;`, a keď práca zlyhá, vyhoďte výnimku.

### Ako vypnúť opakovanie pre jeden handler?

Vráťte `false` z `canRetry()` alebo nech `maxRetry()` vracia `0`.

### Prečo log píše, že sa handler opakuje ako v 5.x?

Handler má `canRetry()` a `maxRetry()`, ale neimplementuje `RetryableHandlerInterface`. Stále sa opakuje, no `canRetry()` sa nevolá. Rozhranie pridajte pred 7.0. Pozrite [Handlery bez rozhrania](#handlery-bez-rozhrania-zastarane).

### Ako dlho trvá do ďalšieho pokusu?

`retries⁴ + 15 + jitter × (retries + 1)` sekúnd, s náhodnou zložkou od 1 do 30, teda pri prvom opakovaní 16 až 45 sekúnd. Potom rýchlo rastie. Aby bolo opakovateľné, odovzdajte `Dispatcher`u `Random\Randomizer` so seedom.

### Prečo sa na RabbitMQ, SQS alebo Kafka opakuje okamžite?

Tieto drivery ignorujú `executeAt`, takže kópia sa publikuje hneď. Na skutočné oneskorenie použite Predis, FileSystem alebo nech handler počká sám.

### Existuje dead-letter fronta?

V Hermese nie. Po poslednom povolenom pokuse sa kópia jednoducho nepošle. Ak na posledné zlyhanie potrebujete reagovať, zalogujte ho v handleri. Pozrite [Logovanie](/sk/guide/logging). Brokery majú vlastnú: redrive policy v SQS a dead-letter exchange v RabbitMQ zachytia správy, ktoré driver odmietne alebo ktoré opakovane zhadzujú workery (pozrite stránky [SQS](/sk/guide/drivers/sqs) a [RabbitMQ](/sk/guide/drivers/rabbitmq)).
