#!/bin/sh
set -e

rm -rf var
composer show typo3/cms-core 2>/dev/null | grep -q "versions : \* v${TYPO3_VERSION}" || rm -rf vendor composer.lock
composer update --no-interaction --no-progress --with "typo3/cms-core:^${TYPO3_VERSION}"
vendor/bin/typo3 setup --force --no-interaction --driver=sqlite --server-type=other \
    --admin-username=admin --admin-user-password='Password1!' --admin-email=admin@example.com --project-name=e2e
php -r '(new PDO("sqlite:" . glob("var/sqlite/*.sqlite")[0]))->exec(file_get_contents("seed.sql"));'
vendor/bin/typo3 cache:flush
chown -R www-data:www-data var config public
touch var/ready

exec php-fpm
