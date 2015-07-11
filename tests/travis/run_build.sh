#!/usr/bin/env bash
set -e
if [ -n "$COVERAGE" ]; then
	vendor/bin/phpunit -c ~/builds/ss/shop_search/phpunit.xml.dist --coverage-clover ~/builds/ss/shop_search/coverage.xml
else
	vendor/bin/phpunit -c ~/builds/ss/shop_search/phpunit.xml.dist
fi
