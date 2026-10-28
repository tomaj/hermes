---
title: "Priorita"
description: "Prioritné fronty v Redis driveroch, ich číselné poradie čítania a worker pre každú prioritu."
---

# Priorita

Priorita je celé číslo, ktoré odovzdáte `emit()` alebo `send()`, a názov, ktorý k nemu priradíte cez `setupPriorityQueue()`. Vyššie čísla sú tie, ktoré chcete spustiť skôr. `Dispatcher::DEFAULT_PRIORITY` je `100`.

Implementujú ju Redis drivery a `FileSystemDriver`. RabbitMQ, SQS a Kafka v `setupPriorityQueue()` vyhodia výnimku a číslo v `send()` ignorujú.

```php
$driver->setupPriorityQueue('hermes_low', Dispatcher::DEFAULT_PRIORITY - 10);
$driver->setupPriorityQueue('hermes_high', Dispatcher::DEFAULT_PRIORITY + 10);

$emitter->emit(new Message('type1', ['a' => 'b']), Dispatcher::DEFAULT_PRIORITY - 10);
$emitter->emit(new Message('type1', ['c' => 'd']), Dispatcher::DEFAULT_PRIORITY + 10);
```

Konštruktor už zaregistroval hlavný kľúč (predvolene `hermes`) s prioritou `100`. Po týchto dvoch volaniach driver pozná tri kľúče: `hermes` so 100, `hermes_low` s 90 a `hermes_high` so 110.

Rovnaké dvojice zaregistrujte aj vo workeri. Nezaregistrovaná priorita vyhodí `UnknownPriorityException` (`Unknown priority 110`, ak 110 nikdy nebola nastavená).

<Diagram name="priority-1" alt="Prioritné fronty sa dajú zaregistrovať v ľubovoľnom poradí; worker ich zoradí a ako prvé číta najvyššie číslo" />

## Poradie čítania

`RedisSetDriver::wait()` a `PredisSetDriver::wait()` zoradia zaregistrované fronty podľa čísla priority, od najvyššej:

```php
$queues = $this->queues;
krsort($queues, SORT_NUMERIC);
```

Na poradí volaní `setupPriorityQueue()` nezáleží. Príklad vyššie zavolá najprv low (90) a potom high (110) a worker vyberá:

1. `hermes_high` (110)
2. `hermes` (100)
3. `hermes_low` (90)

Každá iterácia `wait()` začína znova zhora, takže čakajúca správa so 110 sa vždy spustí pred správou so 100.

### Pred verziou 6.0

Do verzie 5.x drivery používali `array_reverse($this->queues, true)`, čo číta v opačnom poradí registrácie, nie podľa čísla ([issue #50](https://github.com/tomaj/hermes/issues/50)). Konštruktor registruje 100 ako prvú, takže sa vždy čítala posledná a príklad vyššie vyberal 110, potom 90 a potom 100. Vo verzii 5.x registrujte len priority nad 100 vo vzostupnom poradí alebo spustite workera pre každú prioritu.

## Worker pre každú prioritu

`handle()` môže ignorovať všetky fronty okrem tých, ktoré vymenujete:

```php
$dispatcher->handle([Dispatcher::DEFAULT_PRIORITY + 10]);
```

Zoznam obsahuje celé čísla. `Dispatcher::handle()` pretypuje celočíselné reťazce ako `'110'` z argv alebo z voľby konzolového príkazu na `int` (iný reťazec vyhodí `InvalidArgumentException`) a Redis drivery potom porovnávajú striktne (`in_array($priority, $priorities, true)`). Driver volaný priamo cez `wait()` pretypovanie nedostane, tam odovzdajte celé čísla. Prázdny zoznam číta všetky zaregistrované fronty, od najvyššieho čísla.

Ak chcete priority škálovať samostatne, spustite tri CLI procesy:

- jeden `handle([110])`
- jeden `handle([100])`
- jeden `handle([90])`

Procesu so 110 dajte viac replík ako ostatným. Každý proces robí `SPOP` len zo svojho kľúča. Nával práce s nízkou prioritou potom prácu s vysokou prioritou vôbec nezdrží.

## Ďalšie obmedzenia

- Množina nie je zoradená. Dve správy v `hermes_high` sa vyberajú cez `SPOP`, ktorý nie je FIFO.
- Opakovania a presunuté odložené správy volajú `send()` bez priority, takže skončia v kľúči `hermes` so 100.
- JSON správy nemá pole priority. Worker pôvodné číslo z payloadu nezistí.

## Časté otázky

### Ktoré drivery podporujú priority?

`RedisSetDriver`, `PredisSetDriver` a `FileSystemDriver`. RabbitMQ, SQS a Kafka vyhodia v `setupPriorityQueue()` `NotSupportedException`.

### Prečo sa nižšia priorita spustila skôr?

V 6.0 sa nižšie číslo spustí skôr, len keď nečaká žiadna správa s vyššou prioritou. Vo verzii 5.x Redis drivery čítali fronty v opačnom poradí registrácie, nie podľa čísla, takže priorita 100 (ktorú konštruktor registruje prvú) sa čítala posledná. Pozrite issue [#50](https://github.com/tomaj/hermes/issues/50) a [recept s prioritami](/sk/examples/priority).

### Ako dosiahnuť striktné číselné poradie?

Od 6.0 drivery čítajú v číselnom poradí, od najvyššej. Vo verzii 5.x spustite jedného workera pre každú prioritu s `handle([110])`, `handle([100])` a tak ďalej, alebo registrujte len priority nad 100 vo vzostupnom poradí.

### Musím priority registrovať na oboch stranách?

Áno. Emitter aj každý worker volajú `setupPriorityQueue()` s rovnakými dvojicami. Neznáma priorita vyhodí `UnknownPriorityException`.

### Sú správy v rámci jednej priority FIFO?

Nie. Každá priorita je množina v Redise a `SPOP` vracia náhodný prvok.
