vendor/autoload.php:
	composer install

sniff: vendor/autoload.php
	vendor/bin/phpcs --standard=PSR2 src tests examples -n

sniff_fix: vendor/autoload.php
	vendor/bin/phpcbf --standard=PSR2 src tests examples -n

test: vendor/autoload.php
	vendor/bin/phpunit
