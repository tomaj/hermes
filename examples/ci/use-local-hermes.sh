#!/usr/bin/env bash
# Points a Composer project at this checkout of Hermes instead of Packagist:
#   examples/ci/use-local-hermes.sh /path/to/project [package...]
# Adds a path repository for the repository root, requires tomaj/hermes:dev-master from it and
# installs the project with the extra packages. Used by the examples workflow, so a pull request
# runs the examples against its own code. It rewrites the project's composer.json: run it on a copy.
set -euo pipefail
root="$(cd "$(dirname "$0")/../.." && pwd)"
project="${1:?usage: $0 /path/to/project [package...]}"
shift
cd "$project"
# shellcheck disable=SC2016 # $argv is PHP, not shell
# json_encode, so a checkout path with quotes or backslashes still gives valid JSON
repository="$(php -r 'echo json_encode(["type" => "path", "url" => $argv[1], "options" => ["symlink" => false, "versions" => ["tomaj/hermes" => "dev-master"]]], JSON_UNESCAPED_SLASHES);' "$root")"
composer config repositories.hermes "$repository"
composer config minimum-stability dev
composer config prefer-stable true
composer require --no-interaction --no-progress --with-all-dependencies tomaj/hermes:dev-master "$@"
# fail when Composer took Hermes from anywhere else
diff -rq "$root/src" vendor/tomaj/hermes/src > /dev/null \
    || { echo "fail: vendor/tomaj/hermes is not this checkout" >&2; exit 1; }
echo "tomaj/hermes installed from $root"
