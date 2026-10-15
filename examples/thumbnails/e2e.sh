#!/usr/bin/env bash
# End-to-end check: upload one JPEG, the worker writes the three thumbnail sizes.
set -euo pipefail
cd "$(dirname "$0")"
[[ -f vendor/autoload.php ]] || { echo "fail: vendor/ is missing, run make install (composer install) first" >&2; exit 1; }

rm -rf storage
php -S 127.0.0.1:8002 -t public > /tmp/thumbnails-web.log 2>&1 &
WEB=$!
php bin/worker.php > /tmp/thumbnails-worker.log 2>&1 &
WORKER=$!
trap 'kill $WEB $WORKER 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do php -r 'exit(@fsockopen("127.0.0.1", 8002) ? 0 : 1);' 2>/dev/null && break; sleep 0.1; done
php -r 'exit(@fsockopen("127.0.0.1", 8002) ? 0 : 1);' 2>/dev/null && kill -0 "$WEB" 2>/dev/null || { echo "fail: the PHP web server did not start" >&2; exit 1; }

IMAGE="${TMPDIR:-/tmp}/thumbnails-e2e-$$.jpg"
php -r '$i = imagecreatetruecolor(2000, 1500); imagefill($i, 0, 0, imagecolorallocate($i, 40, 120, 200)); imagejpeg($i, $argv[1]);' "$IMAGE"
curl -sf -F "image=@$IMAGE" http://127.0.0.1:8002/
rm -f "$IMAGE"

for _ in $(seq 1 30); do
    if [[ $(ls storage/thumbnails 2>/dev/null | wc -l) -eq 3 ]]; then
        for f in storage/thumbnails/*.jpg; do
            php -r '[$w, $h] = getimagesize($argv[1]); echo basename($argv[1]), " {$w}x{$h}\n";' "$f"
        done
        echo "ok: three thumbnails"
        exit 0
    fi
    sleep 1
done
echo "fail: thumbnails missing" >&2
ls -l storage/thumbnails >&2 || true
cat /tmp/thumbnails-worker.log >&2
exit 1
