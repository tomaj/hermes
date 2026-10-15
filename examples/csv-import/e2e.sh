#!/usr/bin/env bash
# End-to-end check: upload 1234 rows twice, expect 1234 customers.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }
mkdir -p storage
rm -f storage/app.sqlite

php bin/worker.php > /tmp/csv-worker.log 2>&1 &
WORKER=$!
php -S 127.0.0.1:8000 -t public > /dev/null 2>&1 &
WEB=$!
trap 'kill $WORKER $WEB 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);' 2>/dev/null && break; sleep 0.1; done
php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);' 2>/dev/null && kill -0 "$WEB" 2>/dev/null || { echo "fail: the PHP web server did not start" >&2; exit 1; }

php -r '$f = fopen("storage/customers.csv", "w"); fputcsv($f, ["email", "name"]); for ($i = 0; $i < 1234; $i++) { fputcsv($f, ["user$i@example.com", "User $i"]); }'
curl -fsS -F csv=@storage/customers.csv http://127.0.0.1:8000/
curl -fsS -F csv=@storage/customers.csv http://127.0.0.1:8000/

for _ in $(seq 1 20); do
    count=$(php -r '$db = new PDO("sqlite:storage/app.sqlite"); echo (int) @$db->query("SELECT COUNT(*) FROM customers")?->fetchColumn();' 2>/dev/null || echo 0)
    if [ "$count" = 1234 ]; then
        sleep 3 # let the second upload finish too
        count=$(php -r '$db = new PDO("sqlite:storage/app.sqlite"); echo $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();')
        [ "$count" = 1234 ] || { echo "fail: $count customers after the second upload" >&2; exit 1; }
        echo "ok: 1234 customers after two uploads"
        exit 0
    fi
    sleep 1
done
echo "fail: expected 1234 customers, got ${count:-0}" >&2
cat /tmp/csv-worker.log >&2
exit 1
