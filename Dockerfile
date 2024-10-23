# Include ARG before anything else, so the ARG is available for FROM. After "FROM" ARGs are reset!
ARG php_version
FROM php:${php_version}-fpm

# To ensure the variables are available for the rest of the
# script due to the fact "FROM" does reset all ARGs
ARG php_version
ARG php_modules
ARG php_ext_configure

ENV PHP_CONFIGURATION="# Default empty"

# Timezone to be used within the container
ENV TZ="UTC"

USER root

RUN apt-get update && \
    apt-get install --no-install-recommends -y \
        imagemagick ghostscript locales-all libzip4 libpq-dev \
        libzip-dev libcurl4-openssl-dev libpng-dev libwebp-dev libjpeg62-turbo-dev libreadline-dev libicu-dev libonig-dev libfreetype6-dev libxml2-dev && \
    $php_ext_configure && \
    # Unattended install of the redis module
    echo '' | pecl install redis && \
    docker-php-ext-enable redis && \
    docker-php-ext-install gd ${php_modules} && \
    rm -rf /var/cache/apt/* /var/log/* /var/lib/apt/* /usr/share/doc/* && \
    apt-get --purge -y remove gcc cpp g++-* icu-devtools libncurses-dev libstdc++-*-dev make binutils cpp-* linux-libc-dev apt-utils \
    binutils-common && \
    apt-get clean && \
    apt-get -y autoremove && \
    apt-get autoclean

# Add composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && \
    mkdir /app && \
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure PHP
COPY config/typo3.ini /usr/local/etc/php/conf.d/typo3.ini

# Allow ImageMagick 6 to read/write pdf files
COPY config/imagemagick-policy.xml /etc/ImageMagick-6/policy.xml

# USER www-data

WORKDIR /app/

CMD ["/bin/sh", "-c", "echo \"$PHP_CONFIGURATION\" >> /usr/local/etc/php/conf.d/typo3.ini && php-fpm --allow-to-run-as-root"]
