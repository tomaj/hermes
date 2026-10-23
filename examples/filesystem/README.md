# FileSystem driver

Background report exports with no broker: messages are JSON files in `var/hermes/`. Docs: <https://tomaj.github.io/hermes/guide/drivers/filesystem>.

```bash
make install
make worker                         # terminal 1
php bin/emit.php sales              # terminal 2
php bin/emit.php audit 10           # runs about 10 seconds later
php bin/emit.php urgent 0 high      # higher priority queue
php bin/shutdown.php                # stops the worker
```

Each export appends a line to `var/hermes/exports.log`. Set `HERMES_DIR` to use another directory.

`make e2e` checks the priority order, the delayed message and the shutdown in a temporary directory.
