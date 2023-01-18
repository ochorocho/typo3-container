# Include ARG before anything else, so the ARG is available for FROM. After from ARGs are reset!
ARG php_version
FROM php:${php_version}-apache

# To ensure the variables are available for the rest of the
# script due to the fact FROM does reset all ARGs
ARG php_version
ARG php_modules
ARG typo3_version
ARG php_ext_configure

USER root

# Apache config for TYPO3
COPY config/apache-typo3.conf /etc/apache2/sites-available/000-default.conf

RUN apt-get update && \
    apt-get install --no-install-recommends -y \
        gnupg ca-certificates lsb-release imagemagick ghostscript libcurl4 locales-all unzip libzip4 libpq-dev \
        # Install dev packages - @todo: see if these can be removed later on
        libzip-dev libcurl4-openssl-dev libpng-dev libwebp-dev libjpeg62-turbo-dev libreadline-dev libicu-dev libonig-dev libfreetype6-dev libxml2-dev && \
    $php_ext_configure && \
    # @todo: Postgresql: apt install libpq-dev && docker-php-ext-install pgsql pdo_pgsql \
    docker-php-ext-install opcache curl gd zip intl pgsql pdo_pgsql pdo_mysql ${php_modules} && \
    a2enmod alias authz_core autoindex deflate expires filter headers setenvif rewrite && \
    a2ensite 000-default.conf && \
    rm -rf /var/cache/apt/* /var/cache/debconf/* /var/log/dpkg.log && \
    apt-get --purge -y remove gcc cpp g++-10 icu-devtools libbrotli-dev libncurses-dev libstdc++-10-dev make binutils cpp-10 linux-libc-dev && \
    apt-get -y autoremove

# Add composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Configure PHP
COPY config/php.ini /usr/local/etc/php/conf.d/php.ini

# Allow ImageMagick 6 to read/write pdf files
COPY config/imagemagick-policy.xml /etc/ImageMagick-6/policy.xml

COPY config/composer.json /var/www/html/composer.json

# Install TYPO3, so it can be used without a configured volume
RUN sed -i "s/{TYPO3_VERSION}/^${typo3_version}/g;s/\^dev-main/dev-main/g;s/{PHP_VERSION}/${php_version}/g" /var/www/html/composer.json && \
    cd /var/www/html && \
    COMPOSER_ALLOW_SUPERUSER=1 composer install && \
    touch /var/www/html/public/FIRST_INSTALL&& \
    rm -Rf /root/.composer/* && \
    chown -R www-data:www-data /var/www/html

USER www-data

# @todo: Cleanup packages and minimize image size

WORKDIR /var/www/html
