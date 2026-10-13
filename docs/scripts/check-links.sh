#!/usr/bin/env bash
# Check generated diagrams, build the VitePress site, and check links at the real GitHub Pages base.
set -euo pipefail

cd "$(dirname "$0")/.."

port="${DOCS_LINK_PORT:-4173}"
base="/hermes/"

# Generated diagrams must match their docs/diagrams/*.mmd sources.
npm run diagrams:check

python3 scripts/check-snippets.py

npm run docs:build
python3 scripts/check-svg.py .vitepress/dist

if curl -sf -o /dev/null "http://127.0.0.1:${port}${base}"; then
  echo "Port ${port} is already in use" >&2
  exit 1
fi

./node_modules/.bin/vitepress preview --host 127.0.0.1 --port "$port" >/tmp/docs-preview.log 2>&1 &
preview_pid=$!

cleanup() {
  if kill -0 "$preview_pid" 2>/dev/null; then
    kill "$preview_pid" 2>/dev/null || true
    wait "$preview_pid" 2>/dev/null || true
  fi
}
trap cleanup EXIT

ready=0
for _ in $(seq 1 40); do
  if curl -sf -o /dev/null "http://127.0.0.1:${port}${base}"; then
    ready=1
    break
  fi
  if ! kill -0 "$preview_pid" 2>/dev/null; then
    echo "VitePress preview exited before it was ready" >&2
    cat /tmp/docs-preview.log >&2 || true
    exit 1
  fi
  sleep 0.25
done

if [[ "$ready" -ne 1 ]]; then
  echo "VitePress preview did not serve ${base}" >&2
  cat /tmp/docs-preview.log >&2 || true
  exit 1
fi

# Edit links are produced by the VitePress editLink pattern. Requesting each
# one from GitHub trips the rate limit, so check that the file exists instead.
python3 - <<'PY'
import re
import sys
from pathlib import Path

prefix = "https://github.com/tomaj/hermes/edit/master/docs/"
found: set[str] = set()
missing: list[str] = []
for page in Path(".vitepress/dist").rglob("*.html"):
    text = page.read_text(encoding="utf-8", errors="replace")
    for url in re.findall(re.escape(prefix) + r"[^\"'\s#\\]+", text):
        if ":path" in url or not url.endswith(".md"):
            continue
        relative = url[len(prefix):]
        found.add(relative)
        if not Path(relative).is_file():
            missing.append(f"{url} ({page})")
if not found:
    print("No generated GitHub edit links found in the built site", file=sys.stderr)
    sys.exit(1)
if missing:
    print("Edit link target is missing:", file=sys.stderr)
    for item in missing:
        print(item, file=sys.stderr)
    sys.exit(1)
print(f"ok {len(found)} edit links match files on disk")
PY

# Blob and tree links point at this repository. Requesting each one trips
# GitHub's rate limit, and links added by a pull request 404 until it is
# merged, so check that the file or folder exists in the checkout instead.
python3 - <<'PY'
import re
import sys
from pathlib import Path

repo = Path("..")
kinds = {
    "blob": ("https://github.com/tomaj/hermes/blob/master/", Path.is_file),
    "tree": ("https://github.com/tomaj/hermes/tree/master/", Path.is_dir),
}
pages = [(page, page.read_text(encoding="utf-8", errors="replace")) for page in Path(".vitepress/dist").rglob("*.html")]
for kind, (prefix, exists) in kinds.items():
    found: set[str] = set()
    missing: list[str] = []
    for page, text in pages:
        for url in re.findall(re.escape(prefix) + r"[^\"'\s#\\?]+", text):
            relative = url[len(prefix):].rstrip("/")
            found.add(relative)
            if not exists(repo / relative):
                missing.append(f"{url} ({page})")
    if not found:
        print(f"No GitHub {kind} links found in the built site", file=sys.stderr)
        sys.exit(1)
    if missing:
        print(f"{kind.capitalize()} link target is missing:", file=sys.stderr)
        for item in missing:
            print(item, file=sys.stderr)
        sys.exit(1)
    print(f"ok {len(found)} {kind} links match the checkout")
PY

# github.com edit, blob and tree links are skipped so a 429 or 503 from GitHub
# cannot fail the check. Absolute links to the published site (canonical,
# og:url, og:image, sitemap) point at pages this crawl already checks
# locally; new pages only exist there after the next deploy.
skip_args=(
  --skip 'https://github\.com/tomaj/hermes/edit/'
  --skip 'https://github\.com/tomaj/hermes/blob/master/'
  --skip 'https://github\.com/tomaj/hermes/tree/master/'
  --skip 'https://tomaj\.github\.io/hermes/'
)

run_check() {
  npx --no-install linkinator "http://127.0.0.1:${port}${base}" \
    --recurse \
    --retry \
    --concurrency 1 \
    --timeout 20000 \
    --verbosity error \
    "${skip_args[@]}"
}

# 429 is a rate limit, not a broken link. 5xx from GitHub is retried a few times.
attempt=1
while true; do
  set +e
  run_check 2>&1 | tee /tmp/docs-linkinator.txt
  status=${PIPESTATUS[0]}
  set -e
  if [[ "$status" -eq 0 ]]; then
    break
  fi
  mapfile -t codes < <(grep -oE '\[[0-9]{3}\]' /tmp/docs-linkinator.txt | tr -d '[]' | sort -u)
  if [[ ${#codes[@]} -eq 0 ]]; then
    exit "$status"
  fi
  only_rate_limit=1
  retryable=1
  for code in "${codes[@]}"; do
    if [[ "$code" != "429" ]]; then
      only_rate_limit=0
    fi
    case "$code" in
      429|500|502|503|504) ;;
      *) retryable=0 ;;
    esac
  done
  if [[ "$only_rate_limit" -eq 1 ]]; then
    echo "GitHub rate limited some links; not treating HTTP 429 as a failure." >&2
    break
  fi
  if [[ "$retryable" -eq 0 || "$attempt" -ge 3 ]]; then
    exit "$status"
  fi
  echo "GitHub returned a temporary error; retrying the link check (${attempt})..." >&2
  attempt=$((attempt + 1))
  sleep $((attempt * 8))
done
