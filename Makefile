vendor/autoload.php:
	composer install

sniff: vendor/autoload.php
	vendor/bin/phpcs --standard=PSR2 src tests examples -n

sniff_fix: vendor/autoload.php
	vendor/bin/phpcbf --standard=PSR2 src tests examples -n

test: vendor/autoload.php
	vendor/bin/phpunit

coverage: vendor/autoload.php
	mkdir -p build/logs build/coverage
	vendor/bin/phpunit --coverage-clover build/logs/clover.xml --coverage-html build/coverage
