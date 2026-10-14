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
[[ -f config/local.neon ]] && cp config/local.neon /tmp/hermes-nette-local.neon.bak

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

    php bin/console hermes:work > "/tmp/hermes-nette-$driver.log" 2>&1 &
    worker=$!
    sleep 2
    php bin/console app:register-user "$email"

    for _ in $(seq 1 30); do
        grep -q "Welcome email sent to $email" "$log" 2>/dev/null && break
        sleep 1
    done
    if ! grep -q "Welcome email sent to $email" "$log"; then
        echo "fail [$driver]: no log line for $email" >&2
        kill "$worker" 2>/dev/null || true
        cat "/tmp/hermes-nette-$driver.log" >&2
        exit 1
    fi

    php bin/console hermes:restart > /dev/null
    for _ in $(seq 1 15); do
        kill -0 "$worker" 2>/dev/null || break
        sleep 1
    done
    if kill -0 "$worker" 2>/dev/null; then
        echo "fail [$driver]: the worker did not stop after hermes:restart" >&2
        kill "$worker"
        exit 1
    fi
    echo "ok [$driver]: handled $email, worker stopped on hermes:restart"
done

rm -f config/local.neon
[[ -f /tmp/hermes-nette-local.neon.bak ]] && mv /tmp/hermes-nette-local.neon.bak config/local.neon
rm -rf temp/cache
