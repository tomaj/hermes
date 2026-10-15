# Examples

Runnable recipes for Hermes. Each recipe folder has its own `composer.json`, `Makefile`, `README.md` and `e2e.sh`; the walkthroughs are at <https://tomaj.github.io/hermes/examples/>. Framework integrations for Nette, Laravel and Symfony are in [`frameworks/`](frameworks/).

Until Hermes 6.0 is tagged, the recipes require `tomaj/hermes: dev-master` with `"minimum-stability": "dev"` and `"prefer-stable": true`, and the framework guides use `composer require tomaj/hermes:dev-master`. This is temporary: after the 6.0 release they switch to `^6.0`.

`redis/`, `predis/`, `rabbitmq/` and `sqs/` are single-file snippets that load the root `vendor/autoload.php`; run `composer install` in the repository root first.
