---
title: "Náhľady obrázkov s RabbitMQ"
description: "Vytvárajte náhľady obrázkov vo workeri Hermesu na RabbitMQ a čo vo verzii 5.x znamená no_ack a non-durable fronta."
---

# Náhľady obrázkov s RabbitMQ

**Cieľ:** po nahratí vytvoriť vo workeri náhľady v troch veľkostiach. Tento recept používa RabbitMQ cez `LazyRabbitMqDriver`, takže uvidíte, ako broker mení záruky.

<Diagram name="examples-thumbnails-1" alt="Nahraná fotka sa vloží do fronty thumbnails v RabbitMQ. ThumbnailHandler ju prijme a cez GD zapíše náhľady so 160, 640 a 1280 pixelmi." />

<ProjectSource name="thumbnails" :files="['src/ThumbnailHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />

## Súbory

<<< @/../examples/thumbnails/docker-compose.yml{yaml} [docker-compose.yml]

<<< @/../examples/thumbnails/composer.json{json} [composer.json]

<<< @/../examples/thumbnails/src/Hermes.php{php} [src/Hermes.php]

<<< @/../examples/thumbnails/public/index.php{php} [public/index.php]

<<< @/../examples/thumbnails/src/ThumbnailHandler.php{php} [src/ThumbnailHandler.php]

<<< @/../examples/thumbnails/bin/worker.php{php} [bin/worker.php]

## Spustenie

```bash
composer install
docker compose up -d
php bin/worker.php                    # terminal 1
php -S localhost:8003 -t public       # terminal 2
curl -F image=@photo.jpg http://localhost:8003/
ls storage/thumbnails
```

Objavia sa tri súbory: `…-160.jpg`, `…-640.jpg` a `…-1280.jpg`. `make e2e` nahrá vygenerovaný JPEG a skontroluje tri veľkosti. Rozhranie RabbitMQ na `http://localhost:15672` (guest / guest) ukazuje frontu `thumbnails`.

Ak chcete workera zastaviť po aktuálnej správe, touchnite zdieľaný súbor:

```bash
touch /tmp/hermes-thumbnails-shutdown
```

## Úskalia

- **Vo verzii 5.x sa správy potvrdzujú pri prijatí.** Driver v 5.x konzumuje s `no_ack = true`. Ak worker zomrie počas zmenšovania, nahratý obrázok nedostane náhľady. Hermes 6.0 namiesto toho potvrdzuje po handleri, takže sa obrázok doručí znova ([#70](https://github.com/tomaj/hermes/issues/70)).
- **Bez oneskorenia, teda bez skutočného opakovania.** `RetryTrait` funguje, ale kópia sa publikuje hneď a všetky pokusy prebehnú v priebehu sekúnd. Handler ho nepoužíva. Pokazený súbor vráti `false` a plný disk vyhodí výnimku.
- **Vo verzii 5.x fronta nie je durable.** Reštart RabbitMQ zahodí frontu aj s jej správami. Hermes 6.0 ju deklaruje ako durable. Pozrite [RabbitMQ](/sk/guide/drivers/rabbitmq).
- **Bez priorít.** `setupPriorityQueue()` na tomto driveri vyhodí `NotSupportedException`.
- **GD potrebuje pamäť na celý obrázok.** Fotka 6000 × 4000 potrebuje asi 100 MB. `memory_limit` nastavte pre workera, nie pre webovú aplikáciu.

## Celý zdrojový kód

Všetky súbory tohto receptu, s `README.md`, `Makefile` a kontrolou `e2e.sh`, sú v [`examples/thumbnails` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/thumbnails). Naklonujte repozitár, spustite `cd examples/thumbnails` a `make install up`, potom `make e2e`.

## Časté otázky

### Čo sa stane, ak worker zomrie počas zmenšovania?

Vo verzii 5.x tento obrázok nedostane náhľady, pretože RabbitMQ doručenie uzavrel pri prijatí (`no_ack`). Majte príkaz, ktorý znova vloží obrázky bez náhľadov. Vo verzii 6.0 ho RabbitMQ doručí znova.

### Prečo tu nie je opakovanie?

RabbitMQ ignoruje `executeAt`, takže opakovanie by prebehlo hneď. Pokazený obrázok namiesto toho vráti `false`.

### Zachovajú sa správy po reštarte RabbitMQ?

Vo verzii 5.x nie, tá deklaruje non-durable frontu. Od 6.0 je fronta durable a správy sú perzistentné. Pozrite [RabbitMQ](/sk/guide/drivers/rabbitmq).

### Ako zastaviť workera?

Touchnite `/tmp/hermes-thumbnails-shutdown`. `SharedFileShutdown` funguje s akýmkoľvek driverom.

### Koľko pamäte GD potrebuje?

Približne šírka × výška × 4 bajty na dekódovaný obrázok, teda zhruba 100 MB pre fotku 6000 × 4000.
