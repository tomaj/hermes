#!/usr/bin/env bash
# End-to-end check: one request creates one PDF, a second request for the same order does not create another.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }

rm -rf storage/invoices
php -S 127.0.0.1:8001 -t public > /tmp/invoice-web.log 2>&1 &
WEB=$!
php bin/worker.php > /tmp/invoice-worker.log 2>&1 &
WORKER=$!
trap 'kill $WEB $WORKER 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do php -r 'exit(@fsockopen("127.0.0.1", 8001) ? 0 : 1);' 2>/dev/null && break; sleep 0.1; done
php -r 'exit(@fsockopen("127.0.0.1", 8001) ? 0 : 1);' 2>/dev/null && kill -0 "$WEB" 2>/dev/null || { echo "fail: the PHP web server did not start" >&2; exit 1; }

curl -sf -X POST 'http://127.0.0.1:8001/?order=1042'
echo

PDF=storage/invoices/invoice-1042.pdf
for _ in $(seq 1 30); do
    [[ -f $PDF ]] && break
    sleep 1
done
if [[ ! -f $PDF ]] || [[ $(head -c 4 "$PDF") != '%PDF' ]]; then
    echo "fail: $PDF was not created" >&2
    cat /tmp/invoice-worker.log >&2
    exit 1
fi
before=$(php -r 'echo filemtime($argv[1]);' "$PDF")

sleep 1
curl -sf -X POST 'http://127.0.0.1:8001/?order=1042' > /dev/null
sleep 3
if [[ $(php -r 'clearstatcache(); echo filemtime($argv[1]);' "$PDF") != "$before" ]] || [[ -n $(find storage/invoices -name '*.tmp') ]]; then
    echo "fail: the second message rewrote the invoice" >&2
    exit 1
fi
echo "ok: $PDF created once ($(php -r 'echo filesize($argv[1]);' "$PDF") bytes)"
