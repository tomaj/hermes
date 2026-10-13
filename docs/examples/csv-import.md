---
title: "CSV import in chunks"
description: "Import a large CSV in parallel chunks with Hermes workers, safely retried with an upsert."
---

# CSV import in chunks

**Goal:** accept a CSV upload of any size, answer at once, and import it in chunks of 500 rows. Several workers can import chunks in parallel, and a failed chunk is retried on its own.

<ProjectSource name="csv-import" :files="['src/ImportChunkHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />


<Diagram name="examples-csv-import-1" alt="An uploaded customers.csv is counted and split into chunks of 500 rows. Each chunk message goes to ImportChunkHandler, which upserts into SQLite in one transaction a chunk." />

## Files

Redis and `src/Hermes.php` as in the [welcome email tutorial](/examples/welcome-email). Create a `storage/` folder.

<<< @/../examples/csv-import/composer.json{json} [composer.json]

<<< @/../examples/csv-import/public/index.php{php} [public/index.php]

<<< @/../examples/csv-import/src/ImportChunkHandler.php{php} [src/ImportChunkHandler.php]

<<< @/../examples/csv-import/bin/worker.php{php} [bin/worker.php]

## Run it

```bash
composer install
mkdir -p storage
docker compose up -d redis
php bin/worker.php                    # terminal 1
php -S localhost:8002 -t public       # terminal 2
```

Make a test file with 1234 rows and upload it:

```bash
php -r '$f = fopen("customers.csv", "w"); fputcsv($f, ["email", "name"]); for ($i = 0; $i < 1234; $i++) { fputcsv($f, ["user$i@example.com", "User $i"]); }'
curl -F csv=@customers.csv http://localhost:8002/
```

```text
1234 rows queued in 3 chunks.
```

Check the result:

```bash
php -r '$db = new PDO("sqlite:storage/app.sqlite"); echo $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(), "\n";'
```

Upload the same file again. The count stays at 1234, because the import is an upsert.

## Pitfalls

- **Put a reference in the message, not the rows.** The message holds a path, an offset, and a limit. Hundreds of rows in one payload make large Redis values and slow serialization.
- **The file must be readable by the worker.** With workers on other servers, upload to shared storage or S3 and put that key in the message.
- **Chunks run in any order.** A Redis set has no order, and several workers run at once. Do not rely on chunk 0 finishing before chunk 1.
- **Make a chunk safe to run twice.** A thrown exception retries the whole chunk. The transaction plus `ON CONFLICT … DO UPDATE` makes a second run harmless.
- **Know when everything is done.** Hermes has no batch or "all chunks finished" event. Count finished chunks yourself, for example with a Redis `INCR` per chunk, and act when it reaches the total.

## Full source

Every file of this recipe, with its `README.md`, `Makefile` and the `e2e.sh` check, is in [`examples/csv-import` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/csv-import). Clone the repository, `cd examples/csv-import`, and run `make install up`, then `make e2e`.

## FAQ

### Why chunks instead of one message?

Small messages import in parallel, and a failure retries one chunk instead of the whole file.

### Is a retried chunk imported twice?

No. The upsert makes a second run update the same rows.

### How do I know when the import is finished?

Hermes has no batch event. Count finished chunks, for example with Redis `INCR`, and compare with the total.

### Can workers on other servers read the file?

Only from shared storage. Upload to S3 or a shared disk and put that key in the message.

### Do chunks run in order?

No. A Redis set has no order and several workers run at once.
