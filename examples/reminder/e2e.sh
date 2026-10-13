#!/usr/bin/env bash
# End-to-end check: schedule a reminder 5 seconds ahead, wait for it in Mailpit.
set -euo pipefail
cd "$(dirname "$0")"
MAILPIT=${MAILPIT_URL:-http://127.0.0.1:8025}

php bin/worker.php > /tmp/reminder-worker.log 2>&1 &
WORKER=$!
trap 'kill $WORKER 2>/dev/null || true' EXIT

curl -fsS -X DELETE "$MAILPIT/api/v1/messages" > /dev/null
REMIND_IN=5 php public/index.php

sleep 3
if curl -fsS "$MAILPIT/api/v1/messages" | grep -q '"total":1'; then
    echo "fail: the reminder arrived before its executeAt" >&2
    exit 1
fi

for _ in $(seq 1 20); do
    if curl -fsS "$MAILPIT/api/v1/messages" | grep -q 'See you tomorrow'; then
        echo "ok: reminder arrived after its executeAt"
        exit 0
    fi
    sleep 1
done
echo "fail: no reminder after 20 seconds" >&2
cat /tmp/reminder-worker.log >&2
exit 1
