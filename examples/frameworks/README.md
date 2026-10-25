# Framework integration examples

Hermes wired into three frameworks. Each folder holds only the files you add or change in a fresh project, at their paths in that project, plus:

- `install.sh` copies the files into a project: `./install.sh /path/to/project`.
- `e2e.sh` starts the worker, emits a message, waits for the handler's log line and stops the worker with `hermes:restart`, once per driver: `./e2e.sh /path/to/project redis predis rabbitmq sqs`.
- `deploy/hermes-worker.conf` is a Supervisor program for the worker.

| Folder | Framework | Docs |
|---|---|---|
| [`nette`](nette) | Nette 3.2 (`nette/web-project`, nette/di 3.2, contributte/console) | <https://tomaj.github.io/hermes/frameworks/nette> |
| [`laravel`](laravel) | Laravel 11, 12 and 13 | <https://tomaj.github.io/hermes/frameworks/laravel> |
| [`symfony`](symfony) | Symfony 7.4 | <https://tomaj.github.io/hermes/frameworks/symfony> |

All three use the same `DriverFactory`, the same `user.registered` message and the same handler, so the only differences are the framework's container, config and console.

The SQS run of `e2e.sh` needs `HERMES_SQS_ENDPOINT` (for example a local moto server on `http://127.0.0.1:4566`), `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY`.
