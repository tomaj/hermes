---
title: "Driver FileSystem"
description: "FileSystemDriver pre Hermes: fronta v lokálnom adresári bez brokera. FIFO, priority, odložené správy, doručenie at-least-once a visibility timeout."
---

# FileSystem

`Tomaj\Hermes\Driver\FileSystemDriver` drží frontu v lokálnom adresári. Každá správa je jeden JSON súbor. Nepotrebuje broker ani PHP rozšírenie, takže sa hodí na jeden server, malú aplikáciu, CLI nástroj alebo vývojový stroj, kde nechcete spúšťať Redis. Podporuje [priority](/sk/guide/priority), [odložené správy](/sk/guide/delayed) a opakovanie a spracúva správy v poradí FIFO.

```php
public function __construct(
    string $directory,
    string $queue = 'hermes',
    int $refreshInterval = 1,
    int $visibilityTimeout = 300
)
```

- `$directory` sa pri chýbaní vytvorí pri prvom `send()` alebo `wait()`. Priečinky dostanú režim `0775` mínus umask.
- `$queue` je názov priečinka fronty pre `Dispatcher::DEFAULT_PRIORITY`. Názvy front môžu obsahovať písmená, číslice, `.`, `_` a `-` a nesmú začínať `.`. Iné názvy vyhodia `InvalidArgumentException`.
- `$refreshInterval` je, koľko sekúnd `wait()` spí, keď nie je splatná žiadna správa. `0` polluje bez spánku.
- `$visibilityTimeout` je, koľko sekúnd môže zostať prevzatá správa nedokončená, kým sa doručí znova. Nastavte ju nad váš najpomalší handler. `0` nikdy neprinesie opätovné doručenie.

Záporné `$refreshInterval` alebo `$visibilityTimeout` vyhodí `InvalidArgumentException`.

## Vloženie a spracovanie

Toto je `examples/filesystem`, worker, ktorý spúšťa exporty reportov. Stačí `composer install`.

<ProjectSource name="filesystem" :files="['src/Hermes.php', 'src/ExportHandler.php', 'bin/emit.php', 'bin/worker.php', 'bin/shutdown.php', 'e2e.sh']" />

Obe strany skladajú driver na jednom mieste, aby sa emitter a worker zhodli na adresári a názvoch front:

<<< @/../examples/filesystem/src/Hermes.php{php} [src/Hermes.php]

Emitter zapíše súbor a vráti sa:

<<< @/../examples/filesystem/bin/emit.php{php} [bin/emit.php]

Worker používa [`SharedFileShutdown`](/sk/guide/shutdown), ktorý tiež potrebuje len súbor:

<<< @/../examples/filesystem/bin/worker.php{php} [bin/worker.php]

<<< @/../examples/filesystem/bin/shutdown.php{php} [bin/shutdown.php]

## Rozloženie adresára

Každá fronta je priečinok v `$directory` so štyrmi podpriečinkami:

```text
var/hermes/
├── exports/            fronta pre DEFAULT_PRIORITY
│   ├── tmp/            send() najprv píše sem
│   ├── new/            čakajúce správy
│   ├── processing/     prevzaté workerom
│   └── failed/         nepodarilo sa deserializovať
└── exports_high/       jeden priečinok na každé setupPriorityQueue()
```

<Diagram name="drivers-filesystem-1" alt="Životný cyklus správy: send() zapíše do tmp/, premenuje do new/, worker ju prevezme premenovaním do processing/ a po spracovaní zmaže. Zlyhaný callback alebo uplynutý visibility timeout ju vráti do new/; správu, ktorú nemožno deserializovať, presunie do failed/." />

`send()` zapíše JSON do `tmp/` a premenuje ho do `new/`. Worker preto nikdy nečíta napoly zapísaný súbor. Keď zápis alebo premenovanie zlyhá, `send()` vyhodí `FileSystemDriverException`.

Worker prevezme správu premenovaním z `new/` do `processing/`. Po návrate callbacku sa súbor zmaže. Inak:

- **Callback vyhodí výnimku.** Súbor ide späť do `new/` a výnimka zastaví `wait()`, rovnako ako pri ostatných driveroch. Chyby handlerov, ktoré dispatcher zachytí skôr, než dôjdu k driveru, pozrite v [Opakovaní](/sk/guide/retry).
- **Worker zomrie.** Súbor zostane v `processing/`. Prevzatie ho premenuje na `<čas prevzatia>~<názov>`, takže čas prevzatia nastaví to isté atomické premenovanie. Po `$visibilityTimeout` sekundách ho ktorýkoľvek worker vráti do `new/` pod pôvodným názvom.
- **JSON sa nedá deserializovať.** Súbor ide do `failed/` a `wait()` vyhodí `SerializeException`. Tieto súbory skontrolujte alebo zmažte ručne.

Doručenie je at-least-once. Handler, ktorý beží dlhšie ako `$visibilityTimeout`, môže bežať dvakrát, preto robte handlery idempotentné alebo timeout zdvihnite.

## Poradie, oneskorenie a viacerí workeri

Názov súboru začína časom splatnosti správy a potom náhodnou príponou:

```text
1700000000.250000-3f9c1a2b7d4e5f60.json
```

Čas je `executeAt`, alebo čas vytvorenia, keď žiadny nie je. Je doplnený nulami, takže zoradenie názvov zoradí podľa splatnosti. `wait()` číta `new/` v tom poradí, čo robí každú frontu FIFO. Zastaví sa pri prvom súbore, ktorý ešte nie je splatný, takže odložená správa čaká v `new/` bez samostatného plánovača.

<Diagram name="drivers-filesystem-2" alt="Dvaja workeri zdieľajú new/. Obaja sa pokúsia premenovať najstarší splatný súbor; worker A vyhrá a presunie ho do processing/, premenovanie workera B zlyhá a ten vezme ďalší súbor. Súbor, ktorý ešte nie je splatný, ukončí sken." />

`rename()` je atomický na jednom lokálnom súborovom systéme. Keď dvaja workeri premenujú ten istý súbor, jeden vyhrá a druhý ide na ďalší. Na adresári môžete spustiť toľko workerov, koľko chcete.

Priorita funguje ako pri Redis driveroch: najvyššie číslo sa polluje prvé. Worker spustený s `handle([...])` polluje len tieto priority a pri priorite bez fronty vyhodí `UnknownPriorityException`.

## Obmedzenia

- **Jeden server.** `rename()` je atomický len na lokálnom súborovom systéme. Nezdieľajte adresár cez NFS ani iný sieťový súborový systém. Štyri priečinky fronty sú podpriečinky jedného adresára, takže premenovanie nikdy neprechádza medzi súborovými systémami.
- **Jeden súbor na správu.** Desiatky tisíc čakajúcich správ spôsobia, že každý poll číta veľký adresár. Na vysoký objem použite [Redis alebo RabbitMQ](/sk/guide/drivers/).
- **Pollovanie.** Nečinný worker sa zobudí každých `$refreshInterval` sekúnd.
- **Zálohy.** Fronta sú obyčajné súbory. Správa v `processing/` je na ceste. Neobnovujte ju, kým workeri bežia.

## Časté otázky

### Kedy mám použiť FileSystem driver?

Keď emitter a worker bežia na tom istom serveri a nechcete spúšťať broker: malá aplikácia, CLI nástroj, náhrada cronu alebo lokálny vývoj. Porovnajte ho s ostatnými drivermi na stránke [Drivery](/sk/guide/drivers/).

### Môžu viacerí workeri zdieľať jeden adresár?

Áno, na jednom serveri. Každý worker prevezme správu atomickým `rename()`, takže správa sa spracuje raz. Nie na NFS, pozrite [Obmedzenia](#obmedzenia).

### Čo sa stane, keď worker spadne?

Správa zostane v `processing/` a po `$visibilityTimeout` sekundách sa vráti do `new/`, takže ju spracuje iný worker. Pozrite [Rozloženie adresára](#rozlozenie-adresara).

### Sú podporované odložené správy a opakovanie?

Áno. Názov súboru začína časom splatnosti a `wait()` preskakuje súbory, ktoré ešte nie sú splatné. Opakovania z [`RetryTrait`](/sk/guide/retry) sú tiež odložené správy, takže fungujú bez ďalšieho nastavenia. Pozrite [Odložené spracovanie](/sk/guide/delayed).

### Ako zastavím workery?

Použite [`SharedFileShutdown`](/sk/guide/shutdown) so súborom vedľa fronty, ako `examples/filesystem/bin/shutdown.php`. S `ext-pcntl` funguje aj `SIGTERM`.

### Existuje úplný príklad?

Áno. [`examples/filesystem`](https://github.com/tomaj/hermes/tree/master/examples/filesystem) spúšťa exporty reportov a jeho `make e2e` overí poradie priorít, odloženú správu a ukončenie.
