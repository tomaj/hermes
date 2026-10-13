#!/usr/bin/env bash
# End-to-end check: the fake receiver rejects the first attempt, the retry is delivered.
set -euo pipefail
cd "$(dirname "$0")"

php -S 127.0.0.1:9000 receiver.php > /tmp/webhook-receiver.log 2>&1 &
RECEIVER=$!
php bin/worker.php > /tmp/webhook-worker.log 2>&1 &
WORKER=$!
trap 'kill $RECEIVER $WORKER 2>/dev/null || true' EXIT
sleep 1

php public/index.php

# The first retry runs 16 to 45 seconds later.
for _ in $(seq 1 60); do
    if grep -q 'attempt 2 signature ok' /tmp/webhook-receiver.log; then
        echo "ok: delivered on the second attempt"
        exit 0
    fi
    sleep 1
done
echo "fail: no second attempt after 60 seconds" >&2
cat /tmp/webhook-receiver.log /tmp/webhook-worker.log >&2
exit 1
