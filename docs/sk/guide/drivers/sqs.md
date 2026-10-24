---
title: "Driver Amazon SQS"
description: "AmazonSqsDriver pre Hermes: vytvorenie fronty, príjem a mazanie, oprávnenia IAM a čo nepodporuje."
---

# Amazon SQS

`Tomaj\Hermes\Driver\AmazonSqsDriver` potrebuje `aws/aws-sdk-php` 3.x. `composer.json` ho uvádza v `suggest` („Needed for AmazonSqsDriver“) a testy bežia proti `^3.337`. Docblock konštruktora, kód aj `examples/sqs` používajú klienta SDK v3 (`new SqsClient([...])`).

```php
public function __construct(SqsClient $client, string $queueName, array $queueAttributes = [])
```

Konštruktor zavolá `createQueue` a z výsledku si uloží `QueueUrl`:

```php
$result = $client->createQueue([
    'QueueName' => $queueName,
    'Attributes' => $queueAttributes,
]);
$this->queueUrl = $result->get('QueueUrl');
```

IAM principal, ktorý používa emitter aj worker, potrebuje oprávnenie vytvoriť frontu, pretože driver vytvárajú oba procesy. Hodnoty atribútov sú reťazce, ktoré SQS očakáva. Unit test odovzdáva `VisibilityTimeout` ako `'60'`.

## Vloženie a spracovanie

```php
use Aws\Sqs\SqsClient;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\AmazonSqsDriver;
use Tomaj\Hermes\Emitter;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Message;

$client = new SqsClient([
    'version' => 'latest',
    'region' => 'eu-central-1',
    'credentials' => [
        'key' => 'your-key',
        'secret' => 'your-secret',
    ],
]);

$driver = new AmazonSqsDriver($client, 'hermes', [
    'VisibilityTimeout' => '60',
]);

$emitter = new Emitter($driver);
$emitter->emit(new Message('type1', ['message' => 1]));
```

```php
$driver = new AmazonSqsDriver($client, 'hermes');
$dispatcher = new Dispatcher($driver);
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`send()` zavolá `sendMessage` s `QueueUrl` a `MessageBody`. Argument priority sa ignoruje. `setupPriorityQueue()` vyhodí `NotSupportedException` (`AmazonSQS is not supporting priority queues now`). `executeAt` sa nemení na oneskorenie v SQS. Telo sa publikuje hneď a `execute_at` je len vo vnútri JSON.

Nastavenie spania neexistuje. Pri prázdnej fronte worker čaká v `receiveMessage` (`WaitTimeSeconds => 1`, long polling), skontroluje ukončenie a číta znova.

`WaitTimeSeconds` je `1`. Konštruktor môže odovzdať `VisibilityTimeout` do `createQueue` ako reťazcový atribút. Worker nikdy nevolá `ChangeMessageVisibility`. Správu zmaže až po návrate callbacku, takže visibility timeout musí byť dlhší ako váš najpomalší handler. Ak handler zhodí workera alebo callback vyhodí výnimku, správa sa nezmaže a SQS ju po uplynutí visibility timeoutu doručí znova (alebo ju presunie do dead-letter fronty, ak nastavíte redrive policy). Doručenie je at-least-once, preto musia byť handlery idempotentné. Do verzie 5.x driver mazal správu pred handlerom ([issue #69](https://github.com/tomaj/hermes/issues/69)).

<Diagram name="drivers-sqs-1" alt="Postup: worker vytvorí frontu, prijme správu, spustí handler a potom ju zmaže" />

## Príjem a mazanie

<Diagram name="drivers-sqs-2" alt="Postup prijímacej slučky SQS: receiveMessage, deserializácia a callback, potom deleteMessage" />

`wait()` číta `$result['Messages']`. Pre každý záznam deserializuje `Body`, zavolá callback dispatchera a až potom maže podľa `ReceiptHandle`. `incrementProcessedItems()` beží po zmazaní. Ak deserializácia zlyhá, správa sa tiež nezmaže.

Dispatcher výnimky handlerov zachytáva, takže handler, ktorý vyhodí výnimku, sa stále počíta ako spracovaný a jeho správa sa zmaže. [Opakovanie](/sk/guide/retry) môže cez `sendMessage` poslať nové telo. Nové telo je viditeľné hneď, pretože tento driver `executeAt` nerešpektuje.

To platí aj pre PHP `Error` (napríklad `TypeError`) z handlera ([#112](https://github.com/tomaj/hermes/issues/112)). Telo, ktoré sa nedá deserializovať, sa nezmaže: worker skončí a správa sa vráti po uplynutí visibility timeoutu. Pridajte redrive policy, aby takéto správy skončili v dead-letter fronte. Na existujúcej fronte ju nastavte cez `aws sqs set-queue-attributes`, nie cez konštruktor: konštruktor volá `CreateQueue`, ktoré zlyhá s `QueueAlreadyExists`, keď sa jeho atribúty líšia od atribútov fronty.

`$priorities` vo `wait()` sa nepoužíva: `Dispatcher::handle([200])` spracuje každú správu vo fronte, nielen prioritu `200`, a nič nevyhodí.

`SharedFileShutdown` tu funguje. Odovzdajte ho konštruktoru `Dispatcher`a. Driver používa `ShutdownTrait`, takže konštruktor ho prepošle a `wait()` ho kontroluje na začiatku každej slučky.

## Časté otázky

### Akú verziu AWS SDK potrebujem?

`aws/aws-sdk-php` 3.x.

### Prečo emitter potrebuje oprávnenie vytvárať fronty?

Konštruktor volá `createQueue` pri každom vytvorení, vo webovom procese aj vo workeri.

### Chráni visibility timeout pomalý handler?

Áno, od 6.0. Správa sa zmaže až po návrate handlera, takže `VisibilityTimeout` nastavte nad váš najpomalší handler. Inak SQS dá správu inému workerovi, kým ju prvý ešte spracúva.

### Čakajú oneskorenia alebo opakovania?

Nie. `executeAt` sa nemení na oneskorenie v SQS, takže správy aj opakovania sú viditeľné hneď.

### Spí worker pri prázdnej fronte?

Čaká s `WaitTimeSeconds` 1 v `receiveMessage`. Ďalšie nastavenie spania neexistuje.
