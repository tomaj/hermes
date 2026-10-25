---
title: "Hermes v Laraveli"
description: "Používajte Hermes v Laraveli 11, 12 a 13: service provider, config/hermes.php, príkaz workera v Artisane, PSR-3 logovanie, hermes:restart a testy so zamockovaným EmitterInterface."
howto:
  totalTime: PT20M
  tools: [PHP 8.4, Composer, Laravel 11, 12 alebo 13, Redis alebo RabbitMQ alebo SQS]
---

# Laravel

Táto stránka pridá Hermes do nového projektu v Laraveli 11, 12 alebo 13. Controller vloží `user.registered` a `php artisan hermes:work` ju spracuje. Najprv si prečítajte [Integráciu do frameworkov](/sk/frameworks/), kde sú časti spoločné pre všetky frameworky.

<ProjectSource name="frameworks/laravel" :files="['app/Providers/HermesServiceProvider.php', 'config/hermes.php', 'app/Console/Commands/HermesWorkCommand.php', 'app/Http/Controllers/UserController.php']" />

## 1. Inštalácia

```bash
composer create-project laravel/laravel app
cd app
composer require tomaj/hermes:dev-master
composer require predis/predis              # only for the predis driver
composer require php-amqplib/php-amqplib    # only for the rabbitmq driver
composer require aws/aws-sdk-php            # only for the sqs driver
```

Kým Hermes 6.0 nemá tag, vyžadujte `tomaj/hermes:dev-master` ako vyššie; po vydaní prejdite na `^6.0`. Predvolený driver `redis` potrebuje rozšírenie phpredis (`ext-redis`).

## 2. Konfigurácia drivera

`config/hermes.php` číta všetko z `.env`. Nastavte `HERMES_DRIVER` na `redis`, `predis`, `rabbitmq` alebo `sqs` a každej aplikácii dajte vlastnú `HERMES_QUEUE`.

<<< @/../examples/frameworks/laravel/config/hermes.php{php} [config/hermes.php]

`app/Hermes/DriverFactory.php` premení toto pole na driver. Je to ten istý súbor vo všetkých frameworkoch; pozrite [Integrácia do frameworkov](/sk/frameworks/#co-ma-kazda-integracia).

<<< @/../examples/frameworks/laravel/app/Hermes/DriverFactory.php{php} [app/Hermes/DriverFactory.php]

Hermes otvára vlastné spojenie s Redisom namiesto `Redis::connection()`. Spojenie Laravelu pridáva ku každému kľúču `REDIS_PREFIX` a zdieľa ho cache aj fronty Laravelu.

## 3. Registrácia služieb

Provider viaže štyri singletony. Producenti dostávajú `EmitterInterface`; `DispatcherInterface`, kde sa registrujú handlery, si vytvára len príkaz workera.

<<< @/../examples/frameworks/laravel/app/Providers/HermesServiceProvider.php{php} [app/Providers/HermesServiceProvider.php]

Pridajte provider do `bootstrap/providers.php`:

<<< @/../examples/frameworks/laravel/bootstrap/providers.php{php} [bootstrap/providers.php]

Logger je väzba `LoggerInterface` z Laravelu, takže Hermes zapisuje riadky `Start handle message` a `End handle message` do `storage/logs/laravel.log` cez vaše logovacie kanály. Pozrite [Logovanie](/sk/guide/logging).

## 4. Vloženie správy z controllera

<<< @/../examples/frameworks/laravel/app/Http/Controllers/UserController.php{php} [app/Http/Controllers/UserController.php]

<<< @/../examples/frameworks/laravel/routes/web.php{php} [routes/web.php]

`routes/web.php` má ochranu CSRF, takže formulár musí posielať `@csrf` a obyčajný JSON `POST` dostane `419`. Pre JSON API dajte route do `routes/api.php` (`php artisan install:api`).

Správy môže vkladať aj príkaz. Tento je producent, ktorý sa používa nižšie:

<<< @/../examples/frameworks/laravel/app/Console/Commands/RegisterUserCommand.php{php} [app/Console/Commands/RegisterUserCommand.php]

## 5. Napíšte handler

<<< @/../examples/frameworks/laravel/app/Hermes/UserRegisteredHandler.php{php} [app/Hermes/UserRegisteredHandler.php]

Provider ho vytvára cez `$app->make()`, takže jeho konštruktor si môže vypýtať akúkoľvek službu. Výnimkou spustíte opakovanie, vrátením `false` to vzdáte; pozrite [Opakovanie](/sk/guide/retry).

## 6. Spustite workera

Laravel 11, 12 a 13 registrujú každý príkaz v `app/Console/Commands`.

<<< @/../examples/frameworks/laravel/app/Console/Commands/HermesWorkCommand.php{php} [app/Console/Commands/HermesWorkCommand.php]

```bash
php artisan hermes:work                          # terminal 1
php artisan app:register-user jane@example.com   # terminal 2
grep 'Welcome email sent' storage/logs/laravel.log
```

`php artisan hermes:work --priority=200` spracúva len prioritu 200, ak má váš driver frontu s touto prioritou. Pozrite [Priorita](/sk/guide/priority).

## 7. Reštart workerov pri nasadení

<<< @/../examples/frameworks/laravel/app/Console/Commands/HermesRestartCommand.php{php} [app/Console/Commands/HermesRestartCommand.php]

Ako posledný krok nasadenia spustite `php artisan hermes:restart`. Každý worker sa zastaví po aktuálnej správe a Supervisor spustí nový s novým kódom:

<<< @/../examples/frameworks/laravel/deploy/hermes-worker.conf{ini} [deploy/hermes-worker.conf]

systemd a Kubernetes opisuje [Nasadenie do produkcie](/sk/guide/deployment).

## 8. Otestujte producenta

V kontajneri naviažte mock `EmitterInterface`. Od Hermesu 6.0 je `Emitter` `final` a nedá sa zamockovať.

<<< @/../examples/frameworks/laravel/tests/Feature/UserControllerTest.php{php} [tests/Feature/UserControllerTest.php]

Handlery testujte samostatne, bez drivera; pozrite [Testovanie](/sk/guide/testing).

## Emitovanie po commite do databázy {#emitovanie-po-commite-do-databazy}

Ak controller vloží riadok v `DB::transaction()` a emitne o ňom správu, zaregistrujte namiesto `Emitter` [`BufferedEmitter`](/sk/guide/buffered-emitter) a nasmerujte naň alias `EmitterInterface`, aby worker nikdy nehľadal riadok, ktorý ešte nie je commitnutý. V `HermesServiceProvider`:

```php
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tomaj\Hermes\BufferedEmitter;

// register()
$this->app->singleton(
    BufferedEmitter::class,
    static fn(Application $app): BufferedEmitter => new BufferedEmitter(
        new Emitter($app->make(DriverInterface::class), $app->make(LoggerInterface::class)),
        static fn(): bool => DB::transactionLevel() > 0,
    ),
);
$this->app->alias(BufferedEmitter::class, EmitterInterface::class);

// boot()
Event::listen(TransactionCommitted::class, function (TransactionCommitted $event): void {
    if ($event->connection->transactionLevel() === 0) {
        $this->app->make(BufferedEmitter::class)->flush();
    }
});
Event::listen(TransactionRolledBack::class, function (TransactionRolledBack $event): void {
    if ($event->connection->transactionLevel() === 0) {
        $this->app->make(BufferedEmitter::class)->discard();
    }
});
```

Mimo transakcie predikát odosiela hneď. Kontroly `transactionLevel() === 0` zabezpečia, že vnorená `DB::transaction()` nič neodošle ani nezahodí; robí to len najvonkajšia. Správa emitnutá vo vnorenej transakcii, ktorá sa vráti späť, zostane v bufferi a odošle sa po commite vonkajšej transakcie. Predikát sa pozerá na predvolené spojenie; pri viacerých spojeniach porovnávajte v listeneroch aj `$event->connectionName`. Väzba, alias aj listenery boli overené s Laravel 13.38 na SQLite.

## Náhodná zložka opakovania a väzba `Random\Randomizer`

Od Hermesu 6.0 `Dispatcher` prijíma ako štvrtý argument voliteľný `?Random\Randomizer` (pozrite [Integrácia do frameworkov](/sk/frameworks/#nahodna-zlozka-opakovania-a-zaregistrovany-random-randomizer)). Provider vyššie volá `new Dispatcher(...)` s tromi argumentmi, takže väzba `Random\Randomizer` inde v aplikácii časy opakovania nezmení.

Ak triedu namiesto toho získate cez `app(Dispatcher::class)`, Laravel štvrtý argument autowiruje a vloží vašu väzbu `Random\Randomizer`. Ponechajte v provideri `new Dispatcher(...)` alebo pridajte v `register()` kontextovú väzbu, aby dispatcher dostal `null` a zachoval predvolené správanie:

```php
use Random\Randomizer;
use Tomaj\Hermes\Dispatcher;

$this->app->when(Dispatcher::class)->needs(Randomizer::class)->give(fn () => null);
```

## Celý zdrojový kód

Všetky súbory, s `install.sh`, `e2e.sh` a programom pre Supervisor, sú v [`examples/frameworks/laravel` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/frameworks/laravel). Overené s Laravelom 11.57.0, 12.70.0 a 13.38.0 na PHP 8.4, so všetkými drivermi.

## Časté otázky

### Prečo nepoužiť vlastnú frontu Laravelu?

Použite ju, ak vyhovuje. Hermes sa hodí, keď tie isté správy vkladajú alebo spracúvajú iné PHP aplikácie, napríklad služby v Nette alebo Symfony, alebo keď chcete priority Hermesu a jeho rozloženie v Redise. Obe môžu bežať vedľa seba na rôznych kľúčoch; pozrite [Integrácia do frameworkov](/sk/frameworks/#caste-otazky).

### Zastaví `php artisan queue:restart` workery Hermesu?

Nie. Signál reštartu Laravelu je v jeho cache. Workery Hermesu sledujú súbor `SharedFileShutdown`, preto použite `php artisan hermes:restart`. Pozrite [Ukončenie a limit správ](/sk/guide/shutdown).

### Môžem workera spustiť cez `php artisan schedule:work` alebo z jobu?

Nie. `hermes:work` blokuje, kým ho niekto nezastaví. Spúšťajte ho ako samostatný dlhobežiaci proces pod Supervisorom alebo systemd, ako v [Nasadení do produkcie](/sk/guide/deployment).

### Akého klienta Redisu používa?

`HERMES_DRIVER=redis` používa rozšírenie phpredis, `predis` balík Predis. Nezávisí od `REDIS_CLIENT`. Pozrite [Redis](/sk/guide/drivers/redis) a [Predis](/sk/guide/drivers/predis).

### Ako používať SQS lokálne?

Nastavte `HERMES_DRIVER=sqs`, `HERMES_SQS_ENDPOINT=http://127.0.0.1:4566` a ľubovoľné `AWS_ACCESS_KEY_ID` a `AWS_SECRET_ACCESS_KEY` pre lokálny server moto alebo LocalStack. Pozrite [Amazon SQS](/sk/guide/drivers/sqs).
