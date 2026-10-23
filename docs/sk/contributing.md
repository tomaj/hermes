---
title: "Prispievanie"
description: "Ako prispieť do Hermesu: štýl kódu, testy, kontroly a ako meniť diagramy v dokumentácii."
---

# Prispievanie

Pull requesty sú vítané v [tomaj/hermes](https://github.com/tomaj/hermes). Pravidlá projektu sú v [CONTRIBUTING.md](https://github.com/tomaj/hermes/blob/master/CONTRIBUTING.md). Táto stránka sa s týmto súborom zhoduje. Účasť sa riadi [kódexom správania](https://github.com/tomaj/hermes/blob/master/CODE_OF_CONDUCT.md).

## Zmeny, ktoré vieme zlúčiť

- Dodržiavajte [PER Coding Style](https://www.php-fig.org/per/coding-style/). `composer phpcs` kontroluje `src`, `tests` a `examples`.
- Ku každej zmene správania pridajte testy.
- Zmeny správania zdokumentujte v `README.md`, `CHANGELOG.md` a v tejto príručke (v angličtine aj v slovenčine pod `docs/sk/`). Príklady držte na signatúrach v `src/` a opisujte, čo tento kód robí, nie plánovanú neskoršiu verziu. Kam patrí neskoršia verzia, hovorí [Verzie dokumentácie](/sk/guide/versioning).
- [Sémantické verzovanie](https://semver.org/). Aktuálna stabilná rada je 6.x. Nekompatibilné zmeny čakajú na ďalšiu hlavnú verziu.
- Jeden pull request na zmenu, z feature vetvy. Neotvárajte ho z `master`.

## Kontroly

```bash
composer install
composer phpunit
composer phpcs
composer phpstan
composer rector
```

Integračné testy komunikujú so skutočnými brokermi a preskočia sa, ak nie je nastavená ich premenná: `HERMES_TEST_REDIS` (`tcp://127.0.0.1:6379`), `HERMES_TEST_RABBITMQ` (host, port 5672, používateľ `guest`), `HERMES_TEST_SQS` (SQS endpoint, napríklad moto na `http://127.0.0.1:4566`) a `HERMES_TEST_KAFKA` (bootstrap servery, `127.0.0.1:9092`; Kafka testy potrebujú aj `ext-rdkafka`). S `HERMES_REQUIRE_SERVICES=1` chýbajúci alebo nedostupný broker test zlyhá namiesto preskočenia. `compose.yaml` spustí rovnaké verzie brokerov ako CI a `composer test:integration` nastaví všetkých päť premenných:

```bash
docker compose up -d --wait
composer test:integration
```

Pokrytie:

```bash
composer coverage
```

GitHub Actions spúšťa PHPUnit s Redisom, RabbitMQ, moto (SQS) a Kafka na PHP 8.4, 8.4 s najnižšími závislosťami a 8.5, takže sa nepreskočí žiadny integračný test, plus štýl kódu, Rector, PHPStan na úrovni max, `composer audit`, Infection, CodeQL, OpenSSF Scorecard a kontroly diagramov a odkazov v dokumentácii.

## Diagramy

Ručne kreslené diagramy sa generujú. SVG súbory needitujte ručne.

- Zdroj je `docs/diagrams/<name>.json`: ručne rozmiestnený zoznam tvarov, textov, šípok a malých ikon s presnými súradnicami.
- `npm run diagrams` v `docs/` premení každý zdroj na scénu [Excalidraw](https://excalidraw.com/) (`docs/diagrams/<name>.excalidraw`) a na `docs/public/diagrams/<name>-light.svg` a `<name>-dark.svg`. Ručne písaný font Excalifont je vložený v každom SVG.
- Stránka zobrazí diagram cez `<Diagram name="<name>" alt="Čo diagram ukazuje" />`. Svetlý alebo tmavý súbor sa riadi témou stránky. Stránka ho zobrazí najviac 420 pixelov vysoký a kliknutie ho otvorí na celú obrazovku.
- Skript potrebuje Chrome alebo Chromium. Ak je nainštalovaný na nezvyčajnom mieste, nastavte `CHROME_PATH`.
- Commitnite zdroj aj všetky tri vygenerované súbory. `npm run docs:links` (kontrola dokumentácie v CI) najprv spustí `npm run diagrams:check` a zlyhá, keď nie sú aktuálne.

```bash
cd docs
npm ci
npm run diagrams
```

Súbor `.excalidraw` sa dá otvoriť na excalidraw.com na prezretie diagramu, ale zmeny urobené tam sa pri ďalšom behu prepíšu. Namiesto toho zmeňte zdroj `.json`.

## Vydania

Tag verzie (`6.0.0`, bez prefixu `v`) spustí workflow vydania: vezme poznámky k vydaniu zo sekcie danej verzie v `CHANGELOG.md`, zostaví archívy zdrojového kódu, overí pôvod ich zostavenia a publikuje vydanie na GitHube. Kroky sú v [RELEASING.md](https://github.com/tomaj/hermes/blob/master/RELEASING.md).

## Bezpečnosť

Pre zraniteľnosť neotvárajte verejné issue. Použite [súkromné hlásenie zraniteľností](https://github.com/tomaj/hermes/security/advisories/new) alebo, ak formulár nie je dostupný, e-mail [tomasmajer@gmail.com](mailto:tomasmajer@gmail.com). Podporované verzie sú v [SECURITY.md](https://github.com/tomaj/hermes/blob/master/SECURITY.md). 6.x je podporovaná rada; 5.x dostáva len bezpečnostné opravy, do 2027-04-19.

## Licencia

Príspevky sú pod [licenciou MIT](https://github.com/tomaj/hermes/blob/master/LICENSE.md).

## Časté otázky

### Ktoré kontroly musia prejsť?

PHPUnit (so všetkými integračnými testami), PHP-CS-Fixer, PHPStan na úrovni max, Rector, `composer audit`, Infection a kontroly diagramov a odkazov v dokumentácii.

### Ako zmeniť diagram?

Upravte `docs/diagrams/<name>.json`, spustite `npm run diagrams` v `docs/` a commitnite zdroj aj tri vygenerované súbory.

### Môžem zdokumentovať plánovanú funkciu neskoršej hlavnej verzie?

Na týchto stránkach nie: opisujú kód v `src/`. Pozrite [Verzie dokumentácie](/sk/guide/versioning).

### Z ktorej vetvy má pull request prísť?

Z feature vetvy, jedna zmena na pull request.

### Ako sa publikuje vydanie?

Pushnutím tagu verzie. Workflow vydania publikuje vydanie na GitHube s overenými archívmi; pozrite [RELEASING.md](https://github.com/tomaj/hermes/blob/master/RELEASING.md).

### Kde si pozriem náhľad dokumentácie?

Spustite `npm run docs:dev` v `docs/`.
