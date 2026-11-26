---
title: "Zoznam zmien"
description: "Všetky významné zmeny v Hermese, vydanie po vydaní, od roku 2015."
---

# Zoznam zmien

Významné zmeny z [CHANGELOG.md](https://github.com/tomaj/hermes/blob/master/CHANGELOG.md) a z [vydaní na GitHube](https://github.com/tomaj/hermes/releases). Poznámky k [vydaniu 5.0.0](https://github.com/tomaj/hermes/releases/tag/5.0.0) sú sekcia `5.0.0` v `CHANGELOG.md`. Poznámky k vydaniu 6.0.0 sú sekcia `6.0.0`.

Prechádzate z 5.x na 6.0? Postup je v [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md).

## 6.0.0

Minimálne PHP je 8.4. CI beží na 8.4, na 8.4 s najnižšími závislosťami, na 8.5 a na 8.6.

Doručenie je aspoň raz na každom brokeri: RabbitMQ potvrdzuje po handleri a deklaruje trvalú frontu, SQS maže po handleri a Redis drivery znova doručujú odložené správy aj opakovania a prechádzajú priority od najvyššej. Opakovania a odložené správy si ponechajú prioritu. Opakovanie riadi `RetryableHandlerInterface` a jeho `canRetry()`; handler z 5.x s `canRetry()` a `maxRetry()`, ale bez rozhrania, sa stále opakuje ako v 5.x, čo je zastarané a 7.0 to odstráni. `SIGTERM` a `SIGINT` zastavia workera po aktuálnej správe. Chyby drivera sa logujú a znova vyhadzujú. `tracy/tracy` je len odporúčaná a výnimky serializéra už neobsahujú správu. Triedy sú `final` a `readonly`, kde sa dá. Každá nekompatibilná zmena a krok migrácie je v [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md).

### Zastarané v 6.0, odstránené v 7.0

Obe v 6.x stále fungujú a hlásia sa cez `E_USER_DEPRECATED` (potlačené cez `@`) a `warning` v logu `Dispatcher`:

- Handler s `canRetry()` a `maxRetry()`, ktorý neimplementuje `RetryableHandlerInterface`, sa opakuje podľa pravidla z 5.x a `canRetry()` sa nevolá. Implementujte rozhranie. Pozrite [Handlery bez rozhrania](/sk/guide/retry#handlery-bez-rozhrania-zastarane).
- `Dispatcher::emit()`, zastaraná od 2.0. Používajte `Emitter::emit()`. Pozrite [referenciu API](/sk/api/reference#je-dispatcher-emit-stale-podporovana).

## 5.0.0

Minimálne PHP je 7.4. PHP 7.2 a 7.3 vypadli z matice CI. Matica obsahuje 8.0, 8.1, 8.2 a 8.3.

`Message`, `Dispatcher` a `MessageSerializer` používajú typované vlastnosti a prísnejšie kontroly. Zlyhania serializácie JSON vyhadzujú `SerializeException`. Implicitne nullable parametre boli odstránené.

PHPStan beží s prísnymi pravidlami. Pokrytie je lokálne HTML plus Clover a CI publikuje HTML report. Táto stránka dokumentácie servíruje report na `/coverage/` namiesto nahradenia koreňa Pages.

## 4.2.0

`Dispatcher` vie odregistrovať handlery: `unregisterHandler()` a `unregisterAllHandlers()`. Zoznam zmien označuje zmenu rozhrania ako nekompatibilnú.

## 4.1.0

Spracovanie plánu v `RedisSetDriver` bolo upravené pre naplánovanú množinu. Do 5.x jeho `wait()` volal `zRangeByScore` a potom `sPop` na kľúči plánu, čo nikdy nič nepresunulo ([#95](https://github.com/tomaj/hermes/issues/95)). 6.0 používa `ZREM`; pozrite [Odložené spracovanie](/sk/guide/delayed).

## 4.0.1

Opravené plánovanie opakovaní v Predise ([issue #48](https://github.com/tomaj/hermes/issues/48)). `PredisSetDriver::send()` zapisuje zoradenú množinu ako `[$serialized => $executeAt]`.

## 4.0.0

- `Restart*` bolo premenované na `Shutdown*`. Hermes zastaví vlastnú slučku. Znova ho spustí správca procesov.
- Zastaraný `RabbitMqDriver` bol odstránený. Používajte `LazyRabbitMqDriver`.
- `RedisSetDriver` je len pre ext-redis. Kód s Predisom musí používať `PredisSetDriver`.
- `SharedFileShutdown` volá pred čítaním súboru `clearstatcache()`.

## 3.1.0

Podpora mäkkého reštartu v driveroch toho vydania, consumer tag v `LazyRabbitMqDriver` a limit správ plus podpora reštartu pre lenivý RabbitMQ driver a SQS driver.

## 3.0.0

- `RedisRestart` (dnes `RedisShutdown`) zastaví workera cez kľúč v Redise.
- Pribudlo `RestartInterface::restart()`. Premenovanie v 4.0 z tejto myšlienky urobilo `ShutdownInterface::shutdown()`.
- ZeroMQ driver sa presunul do [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver).
- Podpora PHP 7.1 skončila.

## 2.2.3

Vydané na rade 2.x po 3.1.0: `SharedFileRestart` pred kontrolou súboru reštartu vyčistí stat cache PHP, takže dlho bežiaci worker si všimne aj neskorší reštart.

## 2.2.0 až 2.2.2

2.2.0 zaviedlo v `LazyRabbitMqDriver` potvrdenie správy až po handleri, durable frontu a vlastnosti AMQP správy. 2.2.1 sa vrátilo k non-durable fronte a `no_ack = true`, pretože durable deklarácia na existujúcich frontách zlyhávala; takto driver konzumoval až do 6.0. 2.2.2 odstránilo zvyšné potvrdenie po callbacku. Správanie v 6.0 opisuje [RabbitMQ](/sk/guide/drivers/rabbitmq).

## 2.1.0 a 2.1.1

Handlery môžu opakovať (`RetryTrait`). 2.1.1 pridalo `registerHandlers()`, ktoré registruje viac handlerov pre jeden typ, doplnilo `retries` a `execute_at` do kontextu logu dispatchera a opravilo `execute_at` po deserializácii.

## 2.0.0 a 2.0.1

Správy dostali `executeAt`, kód typové deklarácie a minimom sa stalo PHP 7.1. 2.0.1 pridalo `handle()` do `DispatcherInterface`.

## 1.x a staršie

1.3.1 povolilo ľubovoľné `psr/log` 1.x. 1.3.0 zaviedlo `Emitter`, označilo `Dispatcher::emit()` za zastarané a odstránilo ho z `DispatcherInterface` a ukončilo podporu PHP 5.4. 1.2.0 aktualizovalo odporúčanú hlavnú verziu AWS SDK. 1.1.0 pridalo Amazon SQS. 1.0.0 bolo prvé stabilné vydanie a pridalo ack do vtedajšieho RabbitMQ drivera. 0.4.0 pridalo lenivý RabbitMQ driver. 0.3.0 pridalo plynulý reštart a logovanie výnimiek do Tracy. 0.2.0 zmenilo spracovanie návratových hodnôt handlerov. 0.1.0 bolo prvé vydanie s dvoma drivermi.
