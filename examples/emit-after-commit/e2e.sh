#!/usr/bin/env bash
# End-to-end check: SQLite and a directory queue, no services. The worker runs while users register,
# so a message sent before the commit would find no row; a rolled back registration sends nothing.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }
HERMES_DIR=$(mktemp -d)
export HERMES_DIR
LOG=$HERMES_DIR/welcome.log
trap 'kill $WORKER 2>/dev/null || true; rm -rf "$HERMES_DIR"' EXIT

php bin/worker.php > "$HERMES_DIR/worker.log" 2>&1 &
WORKER=$!
sleep 1

out=$(php bin/register.php alice@example.com)
grep -q '^in transaction: 1 buffered, 0 queued$' <<< "$out" || { echo "fail: the message left before the commit: $out" >&2; exit 1; }
out=$(php bin/register.php bob@example.com fail)
grep -q '^rolled back bob@example.com: payment failed, 0 buffered$' <<< "$out" || { echo "fail: the rollback did not discard: $out" >&2; exit 1; }
php bin/register.php carol@example.com > /dev/null

for _ in $(seq 1 15); do [[ -f $LOG && $(wc -l < "$LOG") -ge 2 ]] && break; sleep 1; done
sleep 2
if ! diff <(cat "$LOG" 2>/dev/null) <(printf 'welcome alice@example.com\nwelcome carol@example.com\n'); then
    echo "fail: expected welcome emails for alice and carol only" >&2; cat "$HERMES_DIR/worker.log" >&2; exit 1
fi

php bin/shutdown.php > /dev/null
for _ in $(seq 1 10); do kill -0 "$WORKER" 2>/dev/null || break; sleep 1; done
if kill -0 "$WORKER" 2>/dev/null; then echo "fail: the worker ignored the shutdown file" >&2; exit 1; fi
leftover=$(find "$HERMES_DIR/hermes" -name '*.json' | wc -l)
[[ $leftover -eq 0 ]] || { echo "fail: $leftover message files left behind" >&2; exit 1; }
echo "ok: messages sent after the commit, nothing sent after the rollback"
