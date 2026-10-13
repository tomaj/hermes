# Invoice PDF generation

`public/index.php` emits `invoice.generate` for an order. The worker renders the invoice with dompdf and writes `storage/invoices/invoice-<id>.pdf`. The handler skips orders that already have a PDF, so a retry or a duplicate message never creates a second invoice. Docs: <https://tomaj.github.io/hermes/examples/invoice-pdf>.

```bash
make install
make up                                     # Redis
make worker                                 # terminal 1
make web                                    # terminal 2
curl -X POST 'http://localhost:8000/?order=1042'
ls storage/invoices
```

`make e2e` runs the whole flow and fails if the PDF is missing or a second request for the same order rewrites it.
