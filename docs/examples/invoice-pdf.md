---
title: "Invoice PDF generation"
description: "Generate invoice PDFs in a Hermes worker with dompdf, safely across retries and duplicates."
---

# Invoice PDF generation

**Goal:** when an order is paid, answer the request at once and render the invoice PDF in a worker. A retry or a duplicate message must never produce a second invoice.

<Diagram name="examples-invoice-pdf-1" alt="A confirmed payment emits invoice.generate into Redis. InvoicePdfHandler renders invoice-1042.pdf with dompdf, or returns true when the file already exists." />

<ProjectSource name="invoice-pdf" :files="['src/InvoicePdfHandler.php', 'bin/worker.php', 'public/index.php', 'docker-compose.yml']" />

## Files

The project starts Redis from its own `docker-compose.yml`. `src/Hermes.php` is the same as in the [welcome email tutorial](/examples/welcome-email).

<<< @/../examples/invoice-pdf/src/Hermes.php{php} [src/Hermes.php]

<<< @/../examples/invoice-pdf/composer.json{json} [composer.json]

<<< @/../examples/invoice-pdf/public/index.php{php} [public/index.php]

<<< @/../examples/invoice-pdf/src/InvoicePdfHandler.php{php} [src/InvoicePdfHandler.php]

<<< @/../examples/invoice-pdf/bin/worker.php{php} [bin/worker.php]

## Run it

```bash
composer install
docker compose up -d redis
php bin/worker.php                       # terminal 1
php -S localhost:8001 -t public          # terminal 2
curl -X POST 'http://localhost:8001/?order=1042'
```

`storage/invoices/invoice-1042.pdf` appears within a second. Check it with `pdftotext storage/invoices/invoice-1042.pdf -`.

## Test it

Send the same order twice. The second message finds the file and returns `true` without rendering. Delete the PDF and send it again to render a new one. `make e2e` runs both checks.

## Pitfalls

- **Make the handler idempotent.** A retry carries the same payload. The `is_file()` check plus the write-then-`rename()` means a crash or a retry never leaves two invoices or half a file.
- **Pass ids, not documents.** The message holds `order_id`. The worker loads the current order itself, so the payload stays small and never holds stale data.
- **dompdf uses a lot of memory.** `setMaxProcessItems(200)` lets the worker exit cleanly now and then. Run it under a process manager, as in [the tutorial](/examples/welcome-email#_10-run-it-under-supervisor), so a new one starts.
- **Store files where every worker can read them.** With several servers, write to shared storage or S3 instead of the local disk.

## Full source

Every file of this recipe, with its `README.md`, `Makefile` and the `e2e.sh` check, is in [`examples/invoice-pdf` on GitHub](https://github.com/tomaj/hermes/tree/master/examples/invoice-pdf). Clone the repository, `cd examples/invoice-pdf`, and run `make install up`, then `make e2e`.

## FAQ

### Why check if the file exists?

A retry or a duplicate message must not create a second invoice.

### Why write to a temporary file first?

`rename()` is atomic, so a crash never leaves half a PDF under the final name.

### Why does the worker exit after 200 messages?

dompdf uses a lot of memory. A fresh process keeps memory flat. See [Shutdown and max items](/guide/shutdown).

### Can I email the invoice too?

Yes. Emit a second message from the handler, or send it right after writing the file, as in the [welcome email tutorial](/examples/welcome-email).

### Where should invoices be stored with several servers?

On shared storage or S3, so every worker and the web app can read them.
