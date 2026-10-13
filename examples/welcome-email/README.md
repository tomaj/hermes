# Welcome email

A registration form emits `user.registered`, and a worker sends the welcome email through Symfony Mailer to Mailpit. The step-by-step tutorial is in the docs: <https://tomaj.github.io/hermes/examples/welcome-email>.

Requirements: PHP 8.2+, Composer, Docker with Compose.

```bash
make install   # composer install
make up        # Redis on 6379, Mailpit on 1025 (SMTP) and 8025 (web UI)
make worker    # terminal 1
make web       # terminal 2, http://localhost:8000
```

Register in the form at http://localhost:8000, then open http://localhost:8025 to see the email.

Retry: `docker compose stop mailpit`, register again, `docker compose start mailpit`. The email arrives 16 to 45 seconds later.

Other commands:

```bash
vendor/bin/phpunit tests   # handler test, after composer require --dev phpunit/phpunit
php bin/restart.php        # every running worker exits after its current message
make e2e                   # runs the whole flow and checks Mailpit
make down
```

`deploy/hermes-worker.conf` is a Supervisor program for two workers.
