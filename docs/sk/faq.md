---
title: "Časté otázky"
description: "Odpovede na časté otázky o Hermese: priority, opakovanie, ukončenie, workery, payloady a alternatívy."
---

# Časté otázky

O tom, čo sa so zlyhaním handlera stane, rozhoduje výhradne `Dispatcher`. Broker sa nežiada o nack.

<Diagram name="faq-1" alt="Handler sa vráti alebo vyhodí výnimku. Pri výnimke a zostávajúcich pokusoch sa pošle nová správa, inak je pôvodné doručenie už odstránené" />

## Je priorita argumentom `Message` alebo `emit()`?

`emit()`. `Message::__construct` je `(string $type, ?array $payload = null, ?string $messageId = null, ?float $created = null, ?float $executeAt = null, int $retries = 0)`. Tretí parameter je id. Priorita je druhý argument `Emitter::emit()` a `Driver::send()`.

Číslo na pozícii id prioritu nenastaví. S `declare(strict_types=1)` je to `TypeError`. Bez neho ho PHP zmení na reťazec id, napríklad `"110"`, a správa ide do predvolenej priority. Príklady v README pred 6.0 túto chybu obsahovali.

## Prečo vysoká priorita bežala po nízkej?

Od 6.0 `RedisSetDriver` a `PredisSetDriver` prechádzajú fronty podľa čísla priority, od najvyššej. Do 5.x ich prechádzali v opačnom poradí registrácie, takže predvolená priorita `100`, registrovaná v konštruktore, prišla na rad posledná ([issue #50](https://github.com/tomaj/hermes/issues/50)). Pre 5.x ukazuje [Priorita](/sk/guide/priority) workera s `handle([110])` ako spôsob, ako sa pripnúť na jedno číslo.

RabbitMQ, SQS a Kafka priority neimplementujú. `setupPriorityQueue()` vyhodí `NotSupportedException`.

## Prečo bolo opakovanie na RabbitMQ alebo SQS okamžité?

`executeAt` rešpektujú dva Redis drivery a `FileSystemDriver`. RabbitMQ, SQS a Kafka publikujú okamžite. Kópia na opakovanie aj tak zvýši `retries` a aj tak sa zastaví po `maxRetry()`.

## Vrátil som `false` a správa zmizla

Driver ju už odstránil. `false` správu nevráti do fronty. Výnimka pošle kópiu na opakovanie, len keď handler implementuje `RetryableHandlerInterface` (alebo používa `RetryTrait`), `getRetries() < maxRetry()` a `canRetry()` vráti `true`.

## `canRetry()` vracia `false` a aj tak sa opakuje

Od 6.0 nie: `Dispatcher` volá `canRetry($message, $exception)` a opakovanie preskočí, keď vráti `false` ([#107](https://github.com/tomaj/hermes/issues/107)). Hermes 5.x len kontroloval, či metóda existuje.

## Po aktualizácii sa môj handler prestal opakovať

Definuje `canRetry()` a `maxRetry()` bez implementovania `RetryableHandlerInterface` alebo použitia `RetryTrait`. Pridajte `implements RetryableHandlerInterface` a dajte `canRetry()` parametre `(MessageInterface $message, Throwable $exception)`. Pozrite [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md).

## Neexistuje `Dispatcher::PRIORITY_HIGH`

Jediná konštanta priority je `DEFAULT_PRIORITY = 100`. Komentár v `examples/redis/processor.php`, ktorý spomína `PRIORITY_HIGH`, nezodpovedá triede. Odovzdajte číslo, ktoré ste zaregistrovali, napríklad `Dispatcher::DEFAULT_PRIORITY + 10`.

## Ako spustiť testy?

`composer test` spustí PHPUnit a `composer check` štýl kódu, PHPStan, Rector a testy. [Testovanie](/sk/guide/testing) uvádza všetky skripty, ktoré `composer.json` definuje.

## Reštartuje Hermes workera?

Nie. Od 4.0.0 je táto funkcia ukončenie. `handle()` sa vráti. Proces znova spustí `autorestart` v Supervisore, `Restart=always` v systemd alebo replika v Kubernetes.

## Zabije kľúč ukončenia aj nový proces?

Nie. Všetky tri ukončenia ukladajú čas s mikrosekundami a `shouldShutdown()` vráti true, len keď je tento čas neskorší ako začiatok workera a nie je v budúcnosti. Proces, ktorý začne po ukončení, beží ďalej, aj v tej istej sekunde ([#115](https://github.com/tomaj/hermes/issues/115)). Pri `SharedFileShutdown` chýbajúci súbor tiež znamená bežať ďalej.

## Moja trieda ukončenia zlyhá s „must be compatible with ShutdownInterface“

6.0 typuje `shouldShutdown()` a `shutdown()` cez `DateTimeInterface` namiesto `DateTime` ([#108](https://github.com/tomaj/hermes/issues/108)). Zmeňte oba typy parametrov vo vašej triede na `DateTimeInterface`. Čas začiatku je `DateTimeImmutable`. Pozrite [UPGRADE-6.0.md](https://github.com/tomaj/hermes/blob/master/UPGRADE-6.0.md).

## Kam zmizlo ZeroMQ?

V 3.0.0 sa presunulo z tohto balíka do [tomaj/hermes-zmq-driver](https://github.com/tomaj/hermes-zmq-driver). V `src/Driver` nie je. Vyžaduje `tomaj/hermes` `^2.1`, takže sa s 6.x nenainštaluje; implementujte `DriverInterface` sami (pozrite [Rozširovanie Hermesu](/sk/guide/extending)).

## Ktorý `RabbitMqDriver` mám vytvoriť?

`LazyRabbitMqDriver`. Nelenivý `RabbitMqDriver` bol odstránený v 4.0.0.

## Stránka s pokrytím bývala celou stránkou GitHub Pages

HTML report sa teraz publikuje na [https://tomaj.github.io/hermes/coverage/](https://tomaj.github.io/hermes/coverage/) vedľa tejto príručky. CI workflow stále nahráva artefakt `coverage-report`. Workflow dokumentácie ho skopíruje do artefaktu Pages namiesto nasadenia druhej stránky cez prvú.

## Ako zabrániť zmiznutiu správy?

Pri Redis driveroch nemôžete ponechať kópiu v brokeri, kým sa `handle()` nevráti: Redis a Predis najprv urobia `SPOP`, takže pád po tomto odstránení a pred dokončením handlera doručenie zahodí. Od 6.0 RabbitMQ potvrdzuje až po callbacku ([#70](https://github.com/tomaj/hermes/issues/70)), SQS volá `deleteMessage` až po návrate callbacku ([#69](https://github.com/tomaj/hermes/issues/69)), Kafka commitne offset až po callbacku a `FileSystemDriver` zmaže súbor až po callbacku, takže zlyhaný alebo spadnutý handler nechá správu na opätovné doručenie. Kópia na opakovanie sa publikuje, len keď handler vyhodí výnimku a ešte má pokusy. Praktické prostriedky sú krátky handler, `setMaxProcessItems()`, aby sa proces čisto reštartoval, a handler, ktorý môže bezpečne bežať dvakrát.

## Čo sa stane, keď handler vyhodí výnimku?

`Dispatcher` zachytí akýkoľvek `Throwable` pre každý handler, vrátane PHP `Error`ov ako `TypeError`. Zaloguje chybu. Ak je nainštalovaná Tracy a jej debugger je už zapnutý, zapíše výnimku aj tam. Potom zavolá `retryMessage()`. Ďalšie handlery pre ten istý typ stále bežia. Výnimka sama osebe workera nezastaví. Pozrite [Opakovanie](/sk/guide/retry).

## Zastaví sa worker čisto pri SIGTERM alebo Ctrl+C?

Áno, keď je načítané `ext-pcntl`. Dokončí aktuálnu správu, zaloguje `Exiting hermes dispatcher - received SIGTERM` a vráti sa z `handle()`. Dajte správcovi procesov časový limit na zastavenie dlhší ako jedna správa, pretože potom pošle `SIGKILL`. Pozrite [Ukončenie](/sk/guide/shutdown#sigterm-a-sigint).

## Ako spustiť viac ako jedného workera?

Spustite ten istý CLI skript viackrát. Stačí `numprocs` v Supervisore, niekoľko jednotiek systemd alebo počet replík v Kubernetes. Workery si nevolia lídra. Redis `SPOP`, brokery, Kafka consumer groups a atomické `rename()` v `FileSystemDriver` dajú každé doručenie jednému konzumentovi. Tvar ukazuje [Nasadenie do produkcie](/sk/guide/deployment).

## Ako reštartovať workery po nasadení?

Hermes v bežiacom procese kód neprenačítava. Signalizujte ukončenie (`SharedFileShutdown`, `RedisShutdown` alebo `PredisShutdown`), nechajte `handle()` vrátiť sa a nechajte správcu procesov spustiť nový kód. Worker, ktorého začiatok je po čase ukončenia, beží ďalej, čo je presne to, čo chcete pre novú repliku. Starý kľúč ukončenia v Redise zmažte, len keď chcete, aby ďalší signál bol nový čas. Ponechaný starý kľúč tieto novšie procesy nezastaví.

## Ako otestovať handler bez Redisu?

Vytvorte `Message` a zavolajte `handle()` sami. To je celé rozhranie. Na testovanie registrácie a rozosielania používa testovacia sada `Tomaj\Hermes\Test\Driver\DummyDriver`, ktorý nie je súčasťou `src/`. `unregisterHandler()` a `unregisterAllHandlers()` existujú, aby test mohol znova použiť jeden dispatcher.

## Bude dlhobežiaci worker strácať pamäť?

Hermes uchováva objekty handlerov a počítadlo spracovaných správ. Nespravuje vaše HTTP klienty, súborové handly ani identity mapy ORM. Tie rastú, ak ich váš handler drží. `setMaxProcessItems()` spôsobí, že sa `wait()` vráti po N správach, aby správca procesov mohol spustiť nový proces. To je prepínač recyklácie. Iný limit pamäte knižnica nemá.

## Sú správy FIFO?

Nie na Redise ani Predise: živé fronty sú množiny a `SPOP` vráti jeden náhodný prvok. Dve správy s rovnakou prioritou nemajú poradie. Naprieč prioritami `wait()` prechádza fronty od najvyššieho čísla (5.x používala opačné poradie vkladania, [issue #50](https://github.com/tomaj/hermes/issues/50)). `FileSystemDriver` je v rámci každého prioritného priečinka FIFO. Poradie v RabbitMQ, SQS a Kafka je také, aké tieto brokery majú pri jednej fronte (Kafka: jedna particia je usporiadaná). Hermes nenastavuje FIFO frontu SQS ani skupinu správ.

## Môj handler nenájde riadok, ktorý som práve vložil

Správa odišla skôr, ako sa transakcia commitla, a worker bol rýchlejší ako commit. Obaľte emitter do `BufferedEmitter` a odošlite ho z hooku po commite; pozrite [Emitovanie po commite do databázy](/sk/guide/buffered-emitter).

## Môže tá istá práca prebehnúť dvakrát?

Bežný úspech znova nepublikuje. Opakovanie áno a použije to isté id správy s `retries + 1`. Ak handler už vykonal vedľajší účinok a potom vyhodil výnimku, ďalší pokus ho vykoná znova. Ak proces zomrie po vedľajšom účinku a po odstránení z brokera, pokus sa nezopakuje, lebo nezostalo nič na opakovanie. Ak sa práca nesmie stať dvakrát, berte id správy ako kľúč idempotencie.

## Aký veľký môže byť payload?

Hermes payload zakóduje do JSON a veľkosť neobmedzuje. Payload musí byť pole alebo `null`. Zdroje a otvorené spojenia `json_encode()` neprežijú. Broker môže telo stále odmietnuť. SQS má napríklad vlastný limit. `send()` beží vo webovom procese, takže obrovský payload spomalí danú požiadavku.

## Ako monitorovať Hermes?

Nie je tu port pre metriky ani URL pre health check. Vstavaný signál sú riadky PSR-3: odoslanie, začiatok, koniec, chyba handlera a oznámenie o ukončení. `processed()` je počítadlo v procese. Správca procesov vie, či `worker.php` beží. Všetko ostatné je parser logov alebo metrika, ktorú zvyšujete v handleri.

## Mám použiť Tracy alebo PSR-3 logger?

Oboje, na rôzne veci, a Tracy je voliteľná (`composer require tracy/tracy`). Logger je bežná stopa a vkladá sa do handlerov, ktoré majú `setLogger()`. Tracy dostane výnimku, len keď je `Debugger::isEnabled()` už true. Hermes nikdy nevolá `Debugger::enable()`. S null loggerom sa riadky info a error preskočia. Tracy môže výnimku zaznamenať aj tak, ak ste ju zapli sami.

## Ako sa to porovná so Symfony Messenger alebo Laravel queues?

Tieto frameworky potvrdzujú po handleri, ukladajú zlyhané joby a dodávajú príkaz konzumenta. Redis drivery odstránia doručenie predtým, ako sa `handle()` vráti; RabbitMQ, SQS, Kafka a `FileSystemDriver` ho odstránia až potom. Hermes nemá tabuľku zlyhaných jobov ani príkaz `bin/console`. `worker.php` si napíšete sami. Oneskorenie je `executeAt` na Redis driveroch a na `FileSystemDriver`. Opakovanie je `RetryTrait` plus vzorec v `Dispatcher::nextRetry()`, nie politika opakovania transportu. Middleware, stamps a samostatný transport pre zlyhania nie sú súčasťou tejto knižnice. Hermes použite, keď chcete túto menšiu slučku. Messenger alebo Laravel queues použite, keď chcete potvrdenie po úspechu a ich mechanizmy opakovania.
