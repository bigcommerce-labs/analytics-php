test:
	vendor/phpunit/phpunit/phpunit.php -d date.timezone=Australia/Sydney --colors test/

.PHONY: test
