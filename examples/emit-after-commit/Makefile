.PHONY: install worker register shutdown e2e

install:
	composer install

worker:
	php bin/worker.php

register:
	php bin/register.php alice@example.com

shutdown:
	php bin/shutdown.php

# Runs the whole flow in a temporary directory. No services needed.
e2e:
	./e2e.sh
