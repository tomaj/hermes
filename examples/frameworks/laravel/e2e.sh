#!/usr/bin/env bash
# End-to-end check in a Laravel project that has this integration installed:
#   ./e2e.sh /path/to/project [driver...]
# For each driver: start the worker, emit one message, wait for the handler's log line,
# then stop the worker with hermes:restart.
set -euo pipefail
app="${1:?usage: $0 /path/to/laravel-project [redis predis rabbitmq sqs]}"
shift
drivers=("${@:-redis}")
cd "$app"
log=storage/logs/laravel.log
worker=
cleanup() {
    if [[ -n $worker ]]; then kill "$worker" 2>/dev/null || true; fi
}
trap cleanup EXIT

for driver in "${drivers[@]}"; do
    export HERMES_DRIVER=$driver HERMES_QUEUE="hermes_e2e_${driver}_$$"
    email="laravel-$driver-$RANDOM@example.com"

    # SharedFileShutdown compares whole seconds: a worker started in the same second
    # as the previous hermes:restart would stop at once.
    while [[ $(date +%s) -le ${restarted:-0} ]]; do sleep 0.2; done
    php artisan hermes:work > "/tmp/hermes-laravel-$driver.log" 2>&1 &
    worker=$!
    php artisan app:register-user "$email"

    # The message waits in the queue, so the worker does not have to be ready first.
    for _ in $(seq 1 30); do
        grep -q "Welcome email sent to $email" "$log" 2>/dev/null && break
        kill -0 "$worker" 2>/dev/null || break
        sleep 1
    done
    if ! grep -q "Welcome email sent to $email" "$log"; then
        echo "fail [$driver]: no log line for $email" >&2
        cat "/tmp/hermes-laravel-$driver.log" >&2
        exit 1
    fi

    php artisan hermes:restart > /dev/null
    restarted=$(date +%s)
    for _ in $(seq 1 15); do
        kill -0 "$worker" 2>/dev/null || break
        sleep 1
    done
    if kill -0 "$worker" 2>/dev/null; then
        echo "fail [$driver]: the worker did not stop after hermes:restart" >&2
        exit 1
    fi
    wait "$worker" 2>/dev/null || true
    worker=
    echo "ok [$driver]: handled $email, worker stopped on hermes:restart"
done
