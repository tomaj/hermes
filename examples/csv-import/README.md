# CSV import in chunks

An upload is split into messages of 500 rows. Workers upsert each chunk into SQLite in one transaction. Docs: <https://tomaj.github.io/hermes/examples/csv-import>.

```bash
make install
make up                              # Redis
make worker                          # terminal 1
make web                             # terminal 2
php -r '$f = fopen("customers.csv", "w"); fputcsv($f, ["email", "name"]); for ($i = 0; $i < 1234; $i++) { fputcsv($f, ["user$i@example.com", "User $i"]); }'
curl -F csv=@customers.csv http://localhost:8000/
```

`make e2e` uploads 1234 rows twice and checks for exactly 1234 customers.
