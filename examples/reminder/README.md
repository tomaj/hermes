# Delayed reminder

`public/index.php` emits a reminder with `executeAt` one day before a reservation. The worker sends it to Mailpit when it is due. Docs: <https://tomaj.github.io/hermes/examples/reminder>.

```bash
make install
make up                              # Redis and Mailpit
make worker                          # terminal 1
REMIND_IN=10 php public/index.php    # terminal 2: remind in 10 seconds instead of a day
```

Open http://localhost:8025. "See you tomorrow" arrives about 10 seconds later.

`make e2e` checks that the email does not arrive early and does arrive after its `executeAt`.
