.PHONY: install up down worker web e2e

install:
	composer install

up:
	docker compose up -d

down:
	docker compose down

worker:
	php bin/worker.php

web:
	php -S localhost:8000 -t public

# Runs the whole flow against the services from `make up` and fails when something is missing.
e2e:
	./e2e.sh
