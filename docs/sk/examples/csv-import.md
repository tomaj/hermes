---
title: "Import CSV po častiach"
description: "Importujte veľké CSV v paralelných častiach cez workery Hermesu, s bezpečným opakovaním vďaka upsertu."
---

# Import CSV po častiach

**Cieľ:** prijať nahrané CSV akejkoľvek veľkosti, hneď odpovedať a importovať ho po častiach s 500 riadkami. Viac workerov môže importovať časti paralelne a neúspešná časť sa zopakuje samostatne.

<ProjectSource name="csv-import" :files="['src/ImportChunkHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />


<Diagram name="examples-csv-import-1" alt="Nahrané customers.csv sa spočíta a rozdelí na časti s 500 riadkami. Správa s každou časťou ide do ImportChunkHandler, ktorý ju jednou transakciou upsertne do SQLite." />

## Súbory

Redis a `src/Hermes.php` ako v [návode na uvítací e-mail](/sk/examples/welcome-email). Vytvorte priečinok `storage/`.

<<< @/../examples/csv-import/composer.json{json} [composer.json]

<<< @/../examples/csv-import/public/index.php{php} [public/index.php]

<<< @/../examples/csv-import/src/ImportChunkHandler.php{php} [src/ImportChunkHandler.php]

<<< @/../examples/csv-import/bin/worker.php{php} [bin/worker.php]

## Spustenie

```bash
composer install
mkdir -p storage
docker compose up -d redis
php bin/worker.php                    # terminal 1
php -S localhost:8002 -t public       # terminal 2
```

Vytvorte testovací súbor s 1234 riadkami a nahrajte ho:

```bash
php -r '$f = fopen("customers.csv", "w"); fputcsv($f, ["email", "name"]); for ($i = 0; $i < 1234; $i++) { fputcsv($f, ["user$i@example.com", "User $i"]); }'
curl -F csv=@customers.csv http://localhost:8002/
```

```text
1234 rows queued in 3 chunks.
```

Skontrolujte výsledok:

```bash
php -r '$db = new PDO("sqlite:storage/app.sqlite"); echo $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(), "\n";'
```

Nahrajte ten istý súbor znova. Počet zostane 1234, pretože import je upsert.

## Úskalia

- **Do správy dajte odkaz, nie riadky.** Správa obsahuje cestu, offset a limit. Stovky riadkov v jednom payloade znamenajú veľké hodnoty v Redise a pomalú serializáciu.
- **Worker musí vedieť súbor prečítať.** Ak sú workery na iných serveroch, nahrajte súbor na zdieľané úložisko alebo do S3 a do správy dajte jeho kľúč.
- **Časti bežia v ľubovoľnom poradí.** Množina v Redise nemá poradie a beží viac workerov naraz. Nespoliehajte sa, že časť 0 skončí pred časťou 1.
- **Časť musí byť bezpečné spustiť dvakrát.** Vyhodená výnimka zopakuje celú časť. Transakcia spolu s `ON CONFLICT … DO UPDATE` urobí druhý beh neškodným.
- **Zistite, kedy je všetko hotové.** Hermes nemá dávku ani udalosť „všetky časti skončili“. Hotové časti počítajte sami, napríklad cez Redis `INCR` pre každú časť, a zareagujte, keď počet dosiahne celkový súčet.

## Celý zdrojový kód

Všetky súbory tohto receptu, s `README.md`, `Makefile` a kontrolou `e2e.sh`, sú v [`examples/csv-import` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/csv-import). Naklonujte repozitár, spustite `cd examples/csv-import` a `make install up`, potom `make e2e`.

## Časté otázky

### Prečo časti a nie jedna správa?

Malé správy sa importujú paralelne a zlyhanie zopakuje jednu časť namiesto celého súboru.

### Importuje sa zopakovaná časť dvakrát?

Nie. Vďaka upsertu druhý beh aktualizuje tie isté riadky.

### Ako zistím, že import skončil?

Hermes nemá udalosť dávky. Počítajte hotové časti, napríklad cez Redis `INCR`, a porovnajte s celkovým počtom.

### Môžu súbor čítať workery na iných serveroch?

Len zo zdieľaného úložiska. Nahrajte ho do S3 alebo na zdieľaný disk a do správy dajte jeho kľúč.

### Bežia časti v poradí?

Nie. Množina v Redise nemá poradie a beží viac workerov naraz.
