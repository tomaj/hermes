#!/usr/bin/env bash
# End-to-end check: messages queued before the worker starts come out highest priority first,
# and a worker started with "high" takes only the high queue.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }

run_worker() {
    # Let the worker drain what is queued, then stop it.
    php bin/worker.php "$@" > /tmp/priority-worker.log 2>&1 &
    local pid=$!
    sleep 4
    kill "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
}

run_worker            # empty the queues left by earlier runs
php public/index.php
run_worker
order=$(grep -oE 'email\.[a-z_]+ ' /tmp/priority-worker.log | tr -d ' ' | uniq | paste -sd' ' -)
if [[ $order != 'email.password_reset email.receipt email.newsletter' ]]; then
    echo "fail: order was: $order" >&2
    cat /tmp/priority-worker.log >&2
    exit 1
fi
echo "ok: $order"

php public/index.php
run_worker high
types=$(grep -oE 'email\.[a-z_]+ ' /tmp/priority-worker.log | tr -d ' ' | sort -u | paste -sd' ' -)
run_worker            # take the rest so the next run starts empty
if [[ $types != 'email.password_reset' ]]; then
    echo "fail: the high worker took: $types" >&2
    exit 1
fi
echo "ok: the high worker took only email.password_reset"
