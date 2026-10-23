#!/usr/bin/env bash
# End-to-end check: no broker, just a directory. Priorities, a delayed message and shutdown.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }
HERMES_DIR=$(mktemp -d)
export HERMES_DIR
LOG=$HERMES_DIR/exports.log
trap 'kill $WORKER 2>/dev/null || true; rm -rf "$HERMES_DIR"' EXIT

# queued before the worker starts, so the high priority one must be handled first
php bin/emit.php daily
php bin/emit.php urgent 0 high
php bin/emit.php later 4

php bin/worker.php > "$HERMES_DIR/worker.log" 2>&1 &
WORKER=$!

for _ in $(seq 1 10); do [[ -f $LOG && $(wc -l < "$LOG") -ge 2 ]] && break; sleep 1; done
if ! diff <(awk '{print $3}' "$LOG" 2>/dev/null) <(printf 'urgent\ndaily\n'); then
    echo "fail: expected urgent then daily, the delayed export must wait" >&2; cat "$HERMES_DIR/worker.log" >&2; exit 1
fi

for _ in $(seq 1 15); do grep -q ' later$' "$LOG" && break; sleep 1; done
grep -q ' later$' "$LOG" || { echo "fail: the delayed export never ran" >&2; cat "$HERMES_DIR/worker.log" >&2; exit 1; }

sleep 1
php bin/shutdown.php
for _ in $(seq 1 10); do kill -0 "$WORKER" 2>/dev/null || break; sleep 1; done
if kill -0 "$WORKER" 2>/dev/null; then echo "fail: the worker ignored the shutdown file" >&2; exit 1; fi
leftover=$(find "$HERMES_DIR" -name '*.json' | wc -l)
[[ $leftover -eq 0 ]] || { echo "fail: $leftover message files left behind" >&2; exit 1; }
echo "ok: priority order, delayed export and shutdown"
