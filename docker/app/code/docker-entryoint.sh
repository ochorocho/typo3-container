#!/bin/bash

# Check if .inited  does not exist
if [[ ! -f /var/www/html/public/.inited ]]; then
    echo "=> Setting up Typo3 with defaults for the first time"
    touch /var/www/html/public/.inited

    TYPO3_DB_DRIVER=mysqli \
    TYPO3_DB_USERNAME=typo3 \
    TYPO3_DB_PORT=3306 \
    TYPO3_DB_HOST=mariadb \
    TYPO3_DB_DBNAME=typo3 \
    TYPO3_DB_PASSWORD=typo3 \
    TYPO3_SETUP_ADMIN_EMAIL=admin@local.host \
    TYPO3_SETUP_ADMIN_USERNAME=admin \
    TYPO3_SETUP_ADMIN_PASSWORD=ChangeMe123! \
    TYPO3_SETUP_CREATE_SITE="localhost" \
    TYPO3_PROJECT_NAME="Automated Setup" \
    TYPO3_SERVER_TYPE="other" \
    ./vendor/bin/typo3 setup --force

    # [TODO] TYPO3_SETUP_CREATE_SITE should be dynamic from the starter
fi

TYPO3_ADDITIONAL_PHP_CONFIG_FILE="/var/www/html/config/system/additional.php"

if [[ ! -f $TYPO3_ADDITIONAL_PHP_CONFIG_FILE ]]; then
    touch $TYPO3_ADDITIONAL_PHP_CONFIG_FILE
    echo "<?php
\$GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
\$GLOBALS['TYPO3_CONF_VARS'],
[
    'SYS' => [
        'trustedHostsPattern' => '.*',
    ]
]" > $TYPO3_ADDITIONAL_PHP_CONFIG_FILE

fi
chown -R www-data:www-data /var/www/html

# Run php-fpm?
echo "$PHP_CONFIGURATION" >> /usr/local/etc/php/conf.d/typo3.ini
php-fpm --allow-to-run-as-root
