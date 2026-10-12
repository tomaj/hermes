vendor/autoload.php:
	composer install

sniff: vendor/autoload.php
	vendor/bin/php-cs-fixer fix --dry-run --diff

sniff_fix: vendor/autoload.php
	vendor/bin/php-cs-fixer fix

test: vendor/autoload.php
	vendor/bin/phpunit

coverage: vendor/autoload.php
	mkdir -p build/logs build/coverage
	XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover build/logs/clover.xml --coverage-html build/coverage
