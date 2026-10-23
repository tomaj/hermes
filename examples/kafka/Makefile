.PHONY: install up down worker emit shutdown e2e

install:
	composer install

up:
	docker compose up -d --wait

down:
	docker compose down

worker:
	php bin/worker.php

emit:
	php bin/emit.php 1001

shutdown:
	php bin/shutdown.php

# Runs the whole flow against Kafka from `make up` and fails when something is missing.
e2e:
	./e2e.sh
