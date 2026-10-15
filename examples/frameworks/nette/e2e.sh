#!/usr/bin/env bash
# End-to-end check in a Nette project that has this integration installed:
#   ./e2e.sh /path/to/project [driver...]
# For each driver: write config/local.neon, start the worker, emit one message,
# wait for the handler's log line, then stop the worker with hermes:restart.
# SQS needs HERMES_SQS_ENDPOINT, AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY.
set -euo pipefail
app="${1:?usage: $0 /path/to/nette-project [redis predis rabbitmq sqs]}"
shift
drivers=("${@:-redis}")
cd "$app"
log=log/info.log
backup=$(mktemp "${TMPDIR:-/tmp}/hermes-nette-local.XXXXXX")
had_local=0
if [[ -f config/local.neon ]]; then cp config/local.neon "$backup"; had_local=1; fi
worker=
cleanup() {
    if [[ -n $worker ]]; then kill "$worker" 2>/dev/null || true; fi
    if [[ $had_local == 1 ]]; then mv "$backup" config/local.neon; else rm -f config/local.neon "$backup"; fi
    rm -rf temp/cache
}
trap cleanup EXIT

for driver in "${drivers[@]}"; do
    cat > config/local.neon <<NEON
parameters:
	hermes:
		driver: $driver
		queue: hermes_e2e_${driver}_$$
		sqs:
			endpoint: ${HERMES_SQS_ENDPOINT:-null}
			key: '${AWS_ACCESS_KEY_ID:-}'
			secret: '${AWS_SECRET_ACCESS_KEY:-}'
NEON
    rm -rf temp/cache
    email="nette-$driver-$RANDOM@example.com"

    # SharedFileShutdown compares whole seconds: a worker started in the same second
    # as the previous hermes:restart would stop at once.
    while [[ $(date +%s) -le ${restarted:-0} ]]; do sleep 0.2; done
    php bin/console hermes:work > "/tmp/hermes-nette-$driver.log" 2>&1 &
    worker=$!
    php bin/console app:register-user "$email"

    # The message waits in the queue, so the worker does not have to be ready first.
    for _ in $(seq 1 30); do
        grep -q "Welcome email sent to $email" "$log" 2>/dev/null && break
        kill -0 "$worker" 2>/dev/null || break
        sleep 1
    done
    if ! grep -q "Welcome email sent to $email" "$log"; then
        echo "fail [$driver]: no log line for $email" >&2
        cat "/tmp/hermes-nette-$driver.log" >&2
        exit 1
    fi

    php bin/console hermes:restart > /dev/null
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
