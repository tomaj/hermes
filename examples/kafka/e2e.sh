#!/usr/bin/env bash
# End-to-end check against Kafka (make up): orders reach the worker, and a stopped worker's group resumes
# after the last committed order.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }
APP_VAR=$(mktemp -d)
export APP_VAR
# a fresh group and topic position for every run
KAFKA_GROUP="e2e-$(date +%s)-$$"
export KAFKA_GROUP
LOG=$APP_VAR/orders.log
WORKER=
trap '[[ -n $WORKER ]] && kill $WORKER 2>/dev/null; rm -rf "$APP_VAR"' EXIT

wait_for_lines() {
    for _ in $(seq 1 30); do
        [[ -f $LOG && $(grep -c "order $RUN-" "$LOG") -ge $1 ]] && return 0
        sleep 1
    done
    echo "fail: expected $1 handled orders" >&2; cat "$APP_VAR/worker.log" >&2; exit 1
}

# old runs left orders in the topic; count only this run's
RUN="$(date +%s)$$"
php bin/emit.php "$RUN-1"
php bin/emit.php "$RUN-2"
php bin/worker.php > "$APP_VAR/worker.log" 2>&1 &
WORKER=$!
wait_for_lines 2

php bin/shutdown.php
for _ in $(seq 1 15); do kill -0 "$WORKER" 2>/dev/null || break; sleep 1; done
if kill -0 "$WORKER" 2>/dev/null; then echo "fail: the worker ignored the shutdown file" >&2; exit 1; fi

# the next worker of the group starts after the committed orders
sleep 1
php bin/emit.php "$RUN-3"
php bin/worker.php >> "$APP_VAR/worker.log" 2>&1 &
WORKER=$!
wait_for_lines 3
sleep 2
mine=$(grep -c "order $RUN-" "$LOG")
[[ $mine -eq 3 ]] || { echo "fail: expected each order once, got $mine lines" >&2; cat "$LOG" >&2; exit 1; }
echo "ok: orders handled once, the group resumed after the committed offset"
