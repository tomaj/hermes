# Examples

Runnable recipes for Hermes. Each recipe folder has its own `composer.json`, `Makefile`, `README.md` and `e2e.sh`; the walkthroughs are at <https://tomaj.github.io/hermes/examples/>. Framework integrations for Nette, Laravel and Symfony are in [`frameworks/`](frameworks/).

Until Hermes 6.0 is tagged, the recipes require `tomaj/hermes: dev-master` with `"minimum-stability": "dev"` and `"prefer-stable": true`, and the framework guides use `composer require tomaj/hermes:dev-master`. This is temporary: after the 6.0 release they switch to `^6.0`.

`redis/`, `predis/`, `rabbitmq/` and `sqs/` are single-file snippets that load the root `vendor/autoload.php`; run `composer install` in the repository root first. `filesystem/` is a small project with its own `composer.json` and `e2e.sh` that needs no services. `kafka/` is a small project with its own `composer.json`, `docker-compose.yml` and `e2e.sh`; it needs `ext-rdkafka`.

## Running the checks against this checkout

`ci/` runs the `e2e.sh` scripts against the Hermes code in this repository instead of the Packagist package, the way the `Examples` workflow does on every push and pull request:

```bash
examples/ci/recipe.sh welcome-email                            # one recipe; needs its services from `make up`
examples/ci/recipe.sh filesystem                               # no services
examples/ci/recipe.sh kafka                                    # needs ext-rdkafka and Kafka from examples/kafka/docker-compose.yml or compose.yaml
examples/ci/framework.sh laravel redis predis rabbitmq sqs     # a fresh Laravel (current major), Symfony 7.4 or Nette project
```

`recipe.sh` copies the recipe to a temporary directory and `framework.sh` creates a new project there, so the checkout stays untouched. Both call `ci/use-local-hermes.sh`, which adds a Composer path repository for the repository root and fails when `vendor/tomaj/hermes` is not this checkout. `framework.sh` needs Redis, RabbitMQ and an SQS endpoint (`HERMES_SQS_ENDPOINT`, for example moto) for the drivers you name.
