#!/usr/bin/env bash
# End-to-end check: register through the web form, wait for the email in Mailpit.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }
MAILPIT=${MAILPIT_URL:-http://127.0.0.1:8025}

php bin/worker.php > /tmp/welcome-worker.log 2>&1 &
WORKER=$!
php -S 127.0.0.1:8000 -t public > /dev/null 2>&1 &
WEB=$!
trap 'kill $WORKER $WEB 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);' 2>/dev/null && break; sleep 0.1; done
php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);' 2>/dev/null && kill -0 "$WEB" 2>/dev/null || { echo "fail: the PHP web server did not start" >&2; exit 1; }

curl -fsS -X DELETE "$MAILPIT/api/v1/messages" > /dev/null
curl -fsS -d 'name=E2E&email=e2e@example.com' http://127.0.0.1:8000/
echo

for _ in $(seq 1 20); do
    if curl -fsS "$MAILPIT/api/v1/search?query=to:e2e@example.com" | grep -q '"total":1'; then
        echo "ok: welcome email arrived in Mailpit"
        exit 0
    fi
    sleep 1
done
echo "fail: no email after 20 seconds" >&2
cat /tmp/welcome-worker.log >&2
exit 1
