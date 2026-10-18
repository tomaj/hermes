#!/usr/bin/env bash
# Prints the CHANGELOG.md section of one release, without its heading.
# Usage: .github/scripts/release-notes.sh 6.0.0 [CHANGELOG.md]
# Relative links such as [UPGRADE-6.0.md](UPGRADE-6.0.md) become absolute links to the file at the
# tag (repository from GITHUB_REPOSITORY, default tomaj/hermes), because release notes have no base.
# Exits 1 when CHANGELOG.md has no "## <version> - <date>" heading or the section is empty.
set -euo pipefail

version=${1:?usage: release-notes.sh <version> [changelog]}
changelog=${2:-CHANGELOG.md}

notes=$(awk -v version="$version" '
    index($0, "## " version " - ") == 1 { found = 1; next }
    found && /^## / { exit }
    found { print }
' "$changelog")

# drop leading and trailing blank lines
notes=$(printf '%s\n' "$notes" | sed -e '/./,$!d' | sed -e ':a' -e '/^\n*$/{$d;N;ba' -e '}')

if [[ -z "${notes//[[:space:]]/}" ]]; then
    echo "error: no \"## ${version} - <date>\" section with content in ${changelog}" >&2
    exit 1
fi

repository=${GITHUB_REPOSITORY:-tomaj/hermes}
printf '%s\n' "$notes" | REPOSITORY="$repository" VERSION="$version" perl -pe \
    's{\]\((?![a-z][a-z0-9+.-]*:|[#/])([^)\s]+)\)}{](https://github.com/$ENV{REPOSITORY}/blob/$ENV{VERSION}/$1)}gi'
