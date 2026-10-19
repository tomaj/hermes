#!/usr/bin/env bash
# Creates a fresh framework project, installs the integration from examples/frameworks/ and runs its
# e2e.sh against this checkout of Hermes:
#   examples/ci/framework.sh laravel|symfony|nette [redis predis rabbitmq sqs]
# Needs Redis, RabbitMQ and an SQS endpoint (HERMES_SQS_ENDPOINT, for example moto) for the drivers.
set -euo pipefail
examples="$(cd "$(dirname "$0")/.." && pwd)"
framework="${1:?usage: $0 laravel|symfony|nette [driver...]}"
shift
drivers=("${@:-redis}")
case "$framework" in
    laravel) skeleton=(laravel/laravel '^13.0'); extra=() ;;
    symfony) skeleton=(symfony/skeleton '7.4.*'); extra=(symfony/monolog-bundle) ;;
    nette) skeleton=(nette/web-project '^3.2'); extra=(contributte/console) ;;
    *) echo "fail: unknown framework $framework" >&2; exit 1 ;;
esac
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
composer create-project --no-interaction --no-progress "${skeleton[0]}" "$work/app" "${skeleton[1]}"
"$examples/frameworks/$framework/install.sh" "$work/app"
"$examples/ci/use-local-hermes.sh" "$work/app" "${extra[@]}" predis/predis php-amqplib/php-amqplib aws/aws-sdk-php
"$examples/frameworks/$framework/e2e.sh" "$work/app" "${drivers[@]}"
