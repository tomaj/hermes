---
title: "Nasadenie do produkcie"
description: "Spúšťajte workery Hermesu v produkcii so Supervisorom, systemd alebo Kubernetes, škálujte ich a reštartujte pri nasadení."
---

# Nasadenie do produkcie

Workera spúšťajte ako CLI proces, ktorý smie blokovať. Na webových uzloch nechajte len `Emitter`. Zdieľajte frontu, nie PHP objekty.

<DiagramTabs :tabs="[
  { label: 'Škálovanie', name: 'deployment-2', alt: 'Dve webové repliky vkladajú správy do Redisu, RabbitMQ alebo SQS a tri repliky workerov ich z neho vyberajú' },
  { label: 'Reštart pri nasadení', name: 'deployment-1', alt: 'Webové repliky vkladajú do jedného brokera. Workery spúšťa Supervisor, šablóny jednotiek systemd alebo repliky v Kubernetes a signál na ukončenie spôsobí, že správca spustí nový proces' },
]" />

Každý worker je ten istý skript. Redis `SPOP` dá prvok jednému pripojeniu. RabbitMQ, SQS a Kafka to robia v brokeri; `FileSystemDriver` používa atomické `rename()`. Hermes nevolí lídra.

Škálujete pridávaním procesov. Worker viazaný na jednu prioritu cez `handle([110])` môže mať iný počet replík ako worker viazaný na `100`. Pozrite [Priorita](/sk/guide/priority).

Recyklujte cez `setMaxProcessItems()`, aby proces nebežal týždne. Pri nasadení recyklujte signálom na [ukončenie](/sk/guide/shutdown) a nový kód nechajte spustiť správcu procesov.

Ukážky nižšie používajú Redis workera. Bootstrap vymeňte za Predis, RabbitMQ alebo SQS. Správca procesov sa nemení.

```php
<?php

declare(strict_types=1);

use Redis;
use Tomaj\Hermes\Dispatcher;
use Tomaj\Hermes\Driver\RedisSetDriver;
use Tomaj\Hermes\Handler\EchoHandler;
use Tomaj\Hermes\Shutdown\RedisShutdown;

require __DIR__ . '/vendor/autoload.php';

$redis = new Redis();
$redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1', 6379);

$driver = new RedisSetDriver($redis, 'hermes', 1);
$driver->setMaxProcessItems(1000);

$shutdown = new RedisShutdown($redis);
$dispatcher = new Dispatcher($driver, null, $shutdown);
$dispatcher->registerHandler('type1', new EchoHandler());
$dispatcher->handle();
```

`getenv()` je vaša konfigurácia, nie API Hermesu. Argumenty `RedisSetDriver` zodpovedajú skutočnému konštruktoru: klient, kľúč, interval obnovy.

## Supervisor

[Supervisor](https://supervisord.org/) spustí štyri kópie a keď sa `handle()` vráti, reštartuje ich.

```ini
[program:hermes]
command=php /var/www/worker.php
directory=/var/www
user=www-data
autostart=true
autorestart=true
numprocs=4
process_name=%(program_name)s_%(process_num)02d
stdout_logfile=/var/log/hermes.log
stopwaitsecs=30
```

`supervisorctl stop` a `restart` posielajú `SIGTERM` (`stopsignal`, predvolene `TERM`). S `ext-pcntl` worker dokončí aktuálnu správu a skončí ([Ukončenie](/sk/guide/shutdown#sigterm-a-sigint)). Po `stopwaitsecs` Supervisor pošle `SIGKILL`, preto ho nastavte dlhší ako vaša najpomalšia správa. Pri Redis driveroch sa správa zabitá uprostred handlera stratí, pretože už bola vybratá. Bez `ext-pcntl` pred reštartom spustite jednorazovo `php /var/www/shutdown.php`, ktorý zavolá `RedisShutdown::shutdown()` (alebo touchnite súbor `SharedFileShutdown`).

## systemd

```ini
[Unit]
Description=Hermes worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www
ExecStart=/usr/bin/php /var/www/worker.php
Restart=always
RestartSec=1
# SIGTERM first; SIGKILL after TimeoutStopSec. Longer than the slowest message.
KillSignal=SIGTERM
TimeoutStopSec=60

[Install]
WantedBy=multi-user.target
```

`systemctl stop` pošle `SIGTERM`; s `ext-pcntl` worker dokončí aktuálnu správu a skončí. `TimeoutStopSec` je čas, ktorý systemd čaká pred `SIGKILL`. Predvolený `KillMode=control-group` pošle `SIGTERM` aj každému procesu jednotky, takže ho v tej istej chvíli dostane aj podproces, ktorý spustil handler. `Restart=always` pokrýva čisté skončenie po limite správ aj po ukončení. Ak chcete samostatné filtre priorít, použite šablónu jednotky (`hermes@.service`) alebo viac služieb.

## Kubernetes

Deployment s tromi replikami znamená troch workerov. Keď sa pod zastavuje, Kubernetes spustí `preStop`, potom pošle `SIGTERM` hlavnému procesu kontajnera a `SIGKILL`, keď od začiatku ukončovania uplynie `terminationGracePeriodSeconds` (predvolene 30). S `ext-pcntl` samotný `SIGTERM` spôsobí, že worker dokončí správu a skončí, takže `preStop` je voliteľný. PHP spúšťajte ako hlavný proces (`command: ["php", ...]` alebo `exec` zo shellového entrypointu), inak signál dostane shell a nie PHP. `terminationGracePeriodSeconds` musí pokryť `preStop` aj najpomalšiu správu. Bez `ext-pcntl` `preStop` publikuje kľúč na ukončenie, aby starý pod dokončil správu, ktorú už vybral, a potom sa vrátil z `handle()`.

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: hermes
spec:
  replicas: 3
  selector:
    matchLabels:
      app: hermes
  template:
    metadata:
      labels:
        app: hermes
    spec:
      terminationGracePeriodSeconds: 60
      containers:
        - name: worker
          image: example.invalid/app:5
          command: ["php", "worker.php"]
          env:
            - name: REDIS_HOST
              value: redis
          lifecycle:
            preStop:
              exec:
                command: ["php", "shutdown.php"]
```

`example.invalid` nie je registry. Zoznam príkazov je ten istý `php worker.php` ako pri ostatných správcoch procesov. `shutdown.php` má zavolať `shutdown()` na tom istom kľúči `RedisShutdown`, ktorý worker číta. Nová replika sa spustí po časovej značke a beží ďalej, pretože `shouldShutdown()` vráti false, keď je uložený čas starší ako spustenie procesu.

Readiness probe sa nehodí pre proces, ktorý nikdy neotvorí port. Workera nechajte bez HTTP probe. Liveness môže byť kontrola procesu. Zabitie živého workera zahodí správu, ktorá už bola z fronty odstránená.

## Kontrolný zoznam pred nasadením

- Emitter a worker používajú ten istý driver, kľúč, kľúč plánovača a mapu priorít.
- `handle()` nie je na webovej ceste.
- Správca procesov reštartuje pri akomkoľvek návratovom kóde: limit správ a ukončenie sa vrátia normálne (stav 0) a chyba drivera sa zaloguje a vyhodí znova (nenulový stav).
- Fronty RabbitMQ z tejto knižnice sú od 6.0 durable. Fronta vytvorená verziou 5.x je non-durable; vytvorte ju znova alebo v emitteroch aj workeroch odovzdajte `durable: false` (pozrite [RabbitMQ](/sk/guide/drivers/rabbitmq#prechod-fronty-z-5-x)). RabbitMQ 4.3 a novší predvolene odmieta non-durable fronty, takže tam funguje len durable fronta, ak nenastavíte `deprecated_features.permit.transient_nonexcl_queues = true`.
- Prístupové údaje SQS smú volať `createQueue`, pretože to robí konštruktor drivera.
- Logy idú do PSR-3 loggera. Stdout `EchoHandler` sú len dva riadky, ktoré vypíše cez `echo`.

## Časté otázky

### Koľko workerov spustiť?

Pre handlery viazané na CPU začnite s jedným na jadro, pre handlery viazané na I/O, ako e-mail alebo HTTP, s viacerými. Pridávajte procesy, kým fronta nezostáva krátka.

### Supervisor, systemd alebo Kubernetes?

Funguje ktorýkoľvek. Worker je obyčajný CLI skript, ktorý pri ukončení alebo limite správ skončí, a správca ho spustí znova. Vyberte ten, ktorý už prevádzkujete.

### Ako nasadiť nový kód bez straty správ?

Nechajte správcu procesov zastaviť workery cez `SIGTERM` (vyžaduje `ext-pcntl`) alebo ako posledný krok nasadenia spustite signál na ukončenie. V oboch prípadoch workery dokončia aktuálnu správu, skončia a správca ich spustí s novým kódom. Pozrite [Ukončenie](/sk/guide/shutdown).

### Potrebujú workery port na health check?

Nie. Worker neotvára žiadny port. Použite kontrolu živosti procesu, nie HTTP readiness probe.

### Majú workery bežať aj na webových serveroch?

Môžu, ale `handle()` držte mimo webovej cesty. Webové uzly potrebujú len `Emitter`.
