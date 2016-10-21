#!/bin/bash

touch /logs/php_errors.log

chown www-data:www-data /logs/php_errors.log

# Setup env variables to docker
printenv | perl -pe 's/^(.+?\=)(.*)$/\1"\2"/g' | cat - /crontab_tmp > /crontab

crontab -u www-data /crontab

cron

composer --working-dir=/src install
composer global require "phpunit/phpunit=4.5.*"

php-fpm
