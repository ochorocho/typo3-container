# Include ARG before anything else, so the ARG is available for FROM. After from ARGs are reset!
ARG debian_version
FROM debian:${debian_version}-slim

# To ensure the variables are available for the rest of the
# script due to the fact FROM does reset all ARGs
ARG php_version
ARG php_modules
ARG typo3_version
ARG php_ext_configure
ARG composer_packages_command

USER root

# Copy all config files to the image
COPY config /root/config

RUN apt-get update && \
    apt-get install --no-install-recommends -y \
        gnupg ca-certificates lsb-release imagemagick ghostscript locales-all unzip apt-utils apache2 curl && \
    # Add php
    echo "deb https://packages.sury.org/php bullseye main" > /etc/apt/sources.list.d/sury.list && \
    curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/sury.gpg && \
    apt-get update && \
    apt-get upgrade -y && \
    apt-get install --no-install-recommends -y libapache2-mod-php$php_version $php_modules && \
    # php8.1 php8.1-cli php8.1-curl php8.1-gd php8.1-intl php8.1-mbstring php8.1-mysql php8.1-opcache php8.1-pgsql php8.1-readline php8.1-sqlite3 php8.1-xml php8.1-zip && \
    # Configure apache2
    a2enmod alias authz_core autoindex deflate expires filter headers setenvif rewrite && \
    mv /root/config/apache-typo3.conf /etc/apache2/sites-available/000-default.conf && \
    a2ensite 000-default.conf && \
    # Add composer
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && \
    mv /root/config/composer.json /var/www/html/composer.json && \
    sed -i "s/{TYPO3_VERSION}/^${typo3_version}/g;s/\^dev-main/dev-main/g;s/{PHP_VERSION}/${php_version}/g" /var/www/html/composer.json && \
    cd /var/www/html && \
    COMPOSER_ALLOW_SUPERUSER=1 composer install --optimize-autoloader && \
    COMPOSER_ALLOW_SUPERUSER=1 $composer_packages_command && \
    touch /var/www/html/public/FIRST_INSTALL && \
    chown -R www-data:www-data /var/www/html && \
    # Cleanup
    apt-get --purge -y remove python3.9 apt-utils && \
    apt-get -y autoremove && \
    rm -Rf /root/.composer/* && \
    rm -Rf /root/config/* && \
    rm -rf /var/cache/apt/* /var/cache/debconf/* /var/log/dpkg.log /var/lib/apt /usr/share/perl /usr/share/poppler /usr/share/fonts /usr/share/doc /usr/share/X11 /usr/lib/python3.9

## Configure PHP
COPY config/php.ini /etc/php/$php_version/apache2/conf.d/php.ini

## Allow ImageMagick 6 to read/write pdf files
COPY config/imagemagick-policy.xml /etc/ImageMagick-6/policy.xml
COPY config/apache2-foreground /usr/bin/apache2-foreground

#USER www-data

WORKDIR /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
