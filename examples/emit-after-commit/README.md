# Emit after a database commit

A user registers inside a SQLite transaction and a worker sends the welcome email. `BufferedEmitter` holds the message until the commit, so the worker always finds the row, and drops it on rollback. No services: the queue is a directory (`FileSystemDriver`). Docs: <https://tomaj.github.io/hermes/guide/buffered-emitter>.

```bash
make install
make worker                                   # terminal 1
php bin/register.php alice@example.com        # terminal 2
php bin/register.php bob@example.com fail     # rolled back, nothing is sent
php bin/shutdown.php                          # stops the worker
```

The worker appends to `var/welcome.log`. Set `HERMES_DIR` to use another directory.

`make e2e` checks that nothing is queued before the commit, that a rollback sends nothing, and that the worker finds every row.
