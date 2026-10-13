# Priority queues

`public/index.php` queues a password reset (high), a receipt (normal) and three newsletters (low). The worker takes the highest priority first. `php bin/worker.php high` takes only the high queue, so urgent work has its own process. Docs: <https://tomaj.github.io/hermes/examples/priority>.

```bash
make install
make up                     # Redis
php public/index.php        # queue 5 messages before any worker runs
make worker                 # prints password_reset, receipt, then the newsletters
php bin/worker.php high     # only the high queue
```

`make e2e` checks the order and that the high worker leaves the other queues alone.
