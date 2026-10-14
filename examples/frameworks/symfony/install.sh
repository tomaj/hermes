#!/usr/bin/env bash
# Copy this integration into a Symfony 7.4 project: ./install.sh /path/to/project
set -euo pipefail
src="$(cd "$(dirname "$0")" && pwd)"
app="${1:?usage: $0 /path/to/symfony-project}"

for file in $(cd "$src" && find config src tests -type f); do
    mkdir -p "$app/$(dirname "$file")"
    cp "$src/$file" "$app/$file"
done
echo "Copied the Hermes integration into $app. Now run: composer require tomaj/hermes"
