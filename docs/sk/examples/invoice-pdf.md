---
title: "Faktúra v PDF"
description: "Generujte PDF faktúry vo workeri Hermesu s dompdf, bezpečne pri opakovaniach aj duplicitách."
---

# Faktúra v PDF

**Cieľ:** keď je objednávka zaplatená, odpovedzte na požiadavku hneď a PDF faktúry vygenerujte vo workeri. Opakovanie ani duplicitná správa nesmú nikdy vytvoriť druhú faktúru.

<Diagram name="examples-invoice-pdf-1" alt="Potvrdená platba vloží invoice.generate do Redisu. InvoicePdfHandler vygeneruje invoice-1042.pdf cez dompdf, alebo vráti true, ak súbor už existuje." />

<ProjectSource name="invoice-pdf" :files="['src/InvoicePdfHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />

## Súbory

Projekt spúšťa Redis z vlastného `docker-compose.yml`. `src/Hermes.php` je rovnaký ako v [návode na uvítací e-mail](/sk/examples/welcome-email).

<<< @/../examples/invoice-pdf/src/Hermes.php{php} [src/Hermes.php]

<<< @/../examples/invoice-pdf/composer.json{json} [composer.json]

<<< @/../examples/invoice-pdf/public/index.php{php} [public/index.php]

<<< @/../examples/invoice-pdf/src/InvoicePdfHandler.php{php} [src/InvoicePdfHandler.php]

<<< @/../examples/invoice-pdf/bin/worker.php{php} [bin/worker.php]

## Spustenie

```bash
composer install
docker compose up -d redis
php bin/worker.php                       # terminal 1
php -S localhost:8001 -t public          # terminal 2
curl -X POST 'http://localhost:8001/?order=1042'
```

`storage/invoices/invoice-1042.pdf` sa objaví do sekundy. Skontrolujte ho cez `pdftotext storage/invoices/invoice-1042.pdf -`.

## Test

Pošlite tú istú objednávku dvakrát. Druhá správa nájde súbor a vráti `true` bez generovania. Zmažte PDF a pošlite ju znova, aby sa vygenerovalo nové. `make e2e` spustí obe kontroly.

## Úskalia

- **Handler musí byť idempotentný.** Opakovanie nesie ten istý payload. Kontrola `is_file()` spolu so zápisom a následným `rename()` zaručí, že pád ani opakovanie nikdy nenechajú dve faktúry ani polovicu súboru.
- **Posielajte id, nie dokumenty.** Správa obsahuje `order_id`. Worker si aktuálnu objednávku načíta sám, takže payload zostane malý a nikdy neobsahuje zastarané dáta.
- **dompdf spotrebuje veľa pamäte.** `setMaxProcessItems(200)` nechá workera z času na čas čisto skončiť. Spúšťajte ho pod správcom procesov, ako v [návode](/sk/examples/welcome-email#_10-spustite-to-pod-supervisorom), aby sa spustil nový.
- **Súbory ukladajte tam, kde ich prečíta každý worker.** Pri viacerých serveroch zapisujte na zdieľané úložisko alebo do S3 namiesto lokálneho disku.

## Celý zdrojový kód

Všetky súbory tohto receptu, s `README.md`, `Makefile` a kontrolou `e2e.sh`, sú v [`examples/invoice-pdf` na GitHube](https://github.com/tomaj/hermes/tree/master/examples/invoice-pdf). Naklonujte repozitár, spustite `cd examples/invoice-pdf` a `make install up`, potom `make e2e`.

## Časté otázky

### Prečo kontrolovať, či súbor existuje?

Opakovanie ani duplicitná správa nesmú vytvoriť druhú faktúru.

### Prečo najprv zapisovať do dočasného súboru?

`rename()` je atomický, takže pád nikdy nenechá polovicu PDF pod konečným názvom.

### Prečo worker skončí po 200 správach?

dompdf spotrebuje veľa pamäte. Nový proces drží pamäť na rovnakej úrovni. Pozrite [Ukončenie a limit správ](/sk/guide/shutdown).

### Môžem faktúru aj poslať e-mailom?

Áno. Z handlera vložte druhú správu alebo e-mail pošlite hneď po zapísaní súboru, ako v [návode na uvítací e-mail](/sk/examples/welcome-email).

### Kde ukladať faktúry pri viacerých serveroch?

Na zdieľané úložisko alebo do S3, aby ich mohol čítať každý worker aj webová aplikácia.
