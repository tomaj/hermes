vendor/autoload.php:
	composer install

sniff: vendor/autoload.php
	vendor/bin/phpcs --standard=PSR2 src tests -n

test: vendor/autoload.php
	vendor/bin/phpunit
