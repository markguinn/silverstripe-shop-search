#!/usr/bin/env bash
if [ -n "$COVERAGE" ]; then
	cd shop_search
	wget https://scrutinizer-ci.com/ocular.phar
	php ocular.phar code-coverage:upload -v --format=php-clover ~/builds/ss/shop_search/coverage.xml
fi
