#!/usr/bin/env bash
# Runs one recipe's e2e.sh against this checkout of Hermes: examples/ci/recipe.sh welcome-email
# The recipe is copied to a temporary directory first, so the checkout stays untouched.
# Needs the recipe's services (Redis, Mailpit or RabbitMQ) on localhost, like `make up` starts them.
set -euo pipefail
examples="$(cd "$(dirname "$0")/.." && pwd)"
name="${1:?usage: $0 <recipe>}"
[[ -x "$examples/$name/e2e.sh" ]] || { echo "fail: $examples/$name/e2e.sh not found" >&2; exit 1; }
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
cp -R "$examples/$name" "$work/$name"
rm -rf "$work/$name/vendor" "$work/$name/composer.lock"
"$examples/ci/use-local-hermes.sh" "$work/$name"
"$work/$name/e2e.sh"
