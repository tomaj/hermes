#!/usr/bin/env bash
# Copy this integration into a nette/web-project (Nette 3.2 DI): ./install.sh /path/to/project
set -euo pipefail
src="$(cd "$(dirname "$0")" && pwd)"
app="${1:?usage: $0 /path/to/nette-project}"

for file in $(cd "$src" && find app bin config tests -type f); do
    mkdir -p "$app/$(dirname "$file")"
    cp "$src/$file" "$app/$file"
done
chmod +x "$app/bin/console"
echo "Copied the Hermes integration into $app. Now run: composer require tomaj/hermes:dev-master contributte/console"
