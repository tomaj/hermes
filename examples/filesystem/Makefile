.PHONY: install worker emit shutdown e2e

install:
	composer install

worker:
	php bin/worker.php

emit:
	php bin/emit.php sales

shutdown:
	php bin/shutdown.php

# Runs the whole flow in a temporary directory. No services needed.
e2e:
	./e2e.sh
