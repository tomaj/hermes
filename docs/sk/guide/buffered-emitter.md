---
title: "Emitovanie po commite do databázy"
description: "BufferedEmitter podrží správy Hermes, kým sa databázová transakcia necommitne, a pri rollbacku ich zahodí, takže worker riadok vždy nájde. Zapojenie pre Nette, Laravel a Symfony."
---

# Emitovanie po commite do databázy

Request vloží riadok v transakcii a emitne o ňom správu. Worker si správu môže vyzdvihnúť skôr, ako sa transakcia commitne, hľadať riadok a nenájsť nič. Keď sa transakcia vráti späť, správa je už odoslaná k riadku, ktorý nikdy neexistoval.

`Tomaj\Hermes\BufferedEmitter` (od 6.0) obalí ľubovoľný `EmitterInterface` a drží správy v pamäti, kým nezavoláte `flush()` z hooku po commite. Hook po rollbacku volá `discard()`.

<Diagram name="buffered-emitter-1" alt="BEGIN, INSERT user, emit() nechá správu v bufferi; po commite hook zavolá flush() a worker riadok nájde, po rollbacku discard() a nič sa neodošle" />

```php
use Tomaj\Hermes\BufferedEmitter;
use Tomaj\Hermes\Emitter;

$emitter = new BufferedEmitter(new Emitter($driver, $logger), [$database, 'isInTransaction']);
$database->registerOnCommit([$emitter, 'flush']);
$database->registerOnRollback([$emitter, 'discard']);
```

Producenti ďalej uvádzajú typ `EmitterInterface`; `emit()` vracia `BufferedEmitter`.

## Čo robí ktorá metóda

| Metóda | Správanie |
| --- | --- |
| `__construct(EmitterInterface $emitter, ?callable $shouldBuffer = null)` | `$shouldBuffer` sa volá pri každom `emit()` so správou a prioritou. `true` správu podrží, čokoľvek iné ju hneď odošle. `null` drží vždy. Fungujú closures, `[$object, 'method']` aj iné callables. Callable bez parametrov, napríklad `$pdo->inTransaction(...)`, sa volá bez argumentov, takže vstavané funkcie PHP nevyhodia `ArgumentCountError`. |
| `emit($message, $priority)` | Podrží alebo odošle, vráti `$this`. |
| `flush()` | Odošle buffer vnútornému emitteru v poradí volaní `emit()`, každú správu s jej prioritou. Pri prázdnom bufferi vnútorný emitter nevolá. |
| `discard(): int` | Zahodí buffer bez odoslania a vráti počet zahodených správ. |
| `count(): int` | Počet podržaných správ; `BufferedEmitter` je `Countable`. |

Správu emitnutú počas `flush()`, napríklad listenerom vnútorného emittera, odošle ten istý `flush()`. `flush()` zavolaný počas iného `flush()` hneď skončí, takže sa nič neodošle dvakrát.

## Keď `flush()` vyhodí výnimku

`flush()` sa zastaví na prvej správe, ktorú vnútorný emitter neodošle, zvyčajne preto, že broker nebeží. Správy pred ňou sú odoslané a zostanú odoslané. Neodoslaná správa a zvyšok zostanú v bufferi, `count()` ich ukáže a `flush()` vyhodí tú istú výnimku.

Transakcia je v tej chvíli už commitnutá, takže riadky existujú a správy nie. Nenechajte výnimku zmiznúť:

1. Zalogujte ju, pokojne aj s `count()`.
2. Zavolajte `flush()` znova. Odošle len to, čo zostalo.
3. Keď zlyhá aj to, zavolajte `discard()` a stratené správy riešte tak, ako aplikácia rieši iný zlyhaný vedľajší efekt: cron, ktorý nájde riadky bez príznaku spracovania, alebo alert.

Buffer, ktorý nikto neodošle ani nezahodí, odošle až ďalší úspešný `flush()` v tom istom procese po ďalšom commite. V dlho bežiacom procese (worker, consumer fronty) to môže byť oveľa neskôr, preto vždy skončite jednou z týchto dvoch metód. Dovtedy sú zvyšné správy na nesprávnom mieste: správa emitnutá mimo transakcie sa odošle hneď a predbehne ich a ďalší rollback zavolá `discard()` a zahodí ich, hoci ich riadky sú commitnuté. `discard()` vráti, koľko správ zahodil; keď to nie je `0`, zalogujte to číslo.

## Nie je to transactional outbox

Buffer žije v PHP procese. Keď proces medzi commitom a `flush()` zomrie (fatal error, OOM kill, deploy), správy sa stratia, hoci riadky sú commitnuté. Transactional outbox zapíše správy do tabuľky v tej istej transakcii a odosiela ich samostatný relay, ktorý toto prežije. `BufferedEmitter` to nerobí. Rieši častejšiu chybu, worker rýchlejší ako commit, jednou triedou a bez ďalšej tabuľky.

## Príklad

[`examples/emit-after-commit`](https://github.com/tomaj/hermes/tree/master/examples/emit-after-commit) registruje používateľov do SQLite, uvítaciu správu zaradí cez `FileSystemDriver` a nepotrebuje žiadne služby. `make e2e` spustí workera počas registrácií a overí, že pred commitom nie je nič vo fronte, že vrátená registrácia nič neodošle a že worker nájde každý riadok.

Malý obal nad PDO s callbackmi po commite a rollbacku:

<<< @/../examples/emit-after-commit/src/Database.php{php} [src/Database.php]

Emitter s hookom po commite, ktorý zlyhaný `flush()` zaloguje, raz zopakuje a potom zahodí:

<<< @/../examples/emit-after-commit/src/Hermes.php{php} [src/Hermes.php]

Registrácia; s `fail` ako druhým argumentom sa vráti späť:

<<< @/../examples/emit-after-commit/bin/register.php{php} [bin/register.php]

Handler prečíta riadok, na ktorý správa ukazuje:

<<< @/../examples/emit-after-commit/src/WelcomeHandler.php{php} [src/WelcomeHandler.php]

## Frameworky

- [Nette](/sk/frameworks/nette#emitovanie-po-commite-do-databazy): `registerOnCommit()` a `registerOnRollback()` v `setup` služby.
- [Laravel](/sk/frameworks/laravel#emitovanie-po-commite-do-databazy): listenery `TransactionCommitted` a `TransactionRolledBack`, ktoré kontrolujú `transactionLevel()`.
- [Symfony](/sk/frameworks/symfony#emitovanie-po-commite-do-databazy): Doctrine DBAL nemá callbacky po commite, preto volajte `flush()` po návrate z `transactional()`.

## Testovanie

`BufferedEmitterTest` pokrýva poradie a priority, predikát, callables `[$object, 'method']`, čiastočné zlyhanie s druhým `flush()`, správy emitnuté počas `flush()` aj vnorený `flush()`. `BufferedEmitterIntegrationTest` ho spúšťa pred `Emitter` a `RedisSetDriver`. Vo vlastných testoch skontrolujte `count()` po testovanom kóde a `flush()` alebo `discard()` zavolajte sami.

## Časté otázky

### Potrebujem BufferedEmitter, keď nepoužívam transakcie?

Nie. Bez transakcie je riadok commitnutý skôr, ako sa `emit()` spustí. S predikátom ako `[$database, 'isInTransaction']` ten istý emitter mimo transakcie odosiela hneď, takže ho môžete používať všade.

### Čo pri vnorených transakciách?

Závisí to od hookov. Odosielajte, až keď sa commitne najvonkajšia transakcia, a zahadzujte, až keď sa vráti späť najvonkajšia. Overte, ako vaša služba transakcií volá callbacky; v Laraveli kontrolujte v listeneri `transactionLevel() === 0`. V Laraveli správa emitnutá vo vnorenej transakcii, ktorá sa vrátila späť, zostane v bufferi a odošle sa po commite vonkajšej transakcie.

### Funguje s každým driverom?

Áno. Obaľuje `EmitterInterface`, nie driver, takže funguje s každým driverom aj s vlastným emitterom.

### Zdieľa sa buffer medzi requestami alebo workermi?

Nie. Každá inštancia `BufferedEmitter` má vlastný buffer v pamäti. Použite jednu inštanciu na kontajner, rovnako ako pri `Emitter`.

### Prečo nerozšíriť Emitter?

`Emitter` je od 6.0 `final`. Dekorovanie `EmitterInterface` oddeľuje buffer od odosielania a funguje s ľubovoľným emitterom.
