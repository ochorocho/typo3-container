FROM debian:stable-slim

ARG php_version
# apt packages to install, e.g. "php8.4-gd php8.4-intl"
ARG php_packages
# All PHP modules the image must provide, checked after the install
ARG php_modules

ENV PHP_VERSION=${php_version}
ENV PHP_CONFIGURATION="# Default empty"

# Timezone to be used within the container
ENV TZ="UTC"

ENV DEBIAN_FRONTEND=noninteractive

USER root

# Add the Sury PHP repository
RUN apt-get update && \
    apt-get install --no-install-recommends -y curl ca-certificates && \
    curl -fsSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb && \
    dpkg -i /tmp/debsuryorg-archive-keyring.deb && \
    rm /tmp/debsuryorg-archive-keyring.deb && \
    . /etc/os-release && \
    echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $VERSION_CODENAME main" > /etc/apt/sources.list.d/php.list

RUN apt-get update && \
    apt-get install --no-install-recommends -y \
        php${php_version}-fpm ${php_packages} \
        imagemagick ghostscript locales-all && \
    rm -rf /var/lib/apt/lists/* /var/cache/apt/*

# Fail the build if a required module is missing. "php -m" lists opcache as "Zend OPcache"
RUN for module in ${php_modules}; do \
        php -m | grep -qiE "^(zend )?${module}$" || { echo "PHP module ${module} is missing" >&2; exit 1; }; \
    done

# Add composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer && \
    mkdir /app && \
    ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# Configure PHP for cli and fpm
COPY config/typo3.ini /etc/php/${php_version}/mods-available/typo3.ini
RUN phpenmod typo3

# Configure php-fpm for running in a container
COPY config/php-fpm-docker.conf /etc/php/${php_version}/fpm/pool.d/zz-docker.conf
# Usually created by systemd-tmpfiles, php-fpm needs it for the pid file
RUN mkdir -p /run/php

# Allow ImageMagick to read/write pdf files. The major version depends on the Debian release of the base image.
COPY config/imagemagick-policy.xml /tmp/imagemagick-policy.xml
RUN cp /tmp/imagemagick-policy.xml /etc/ImageMagick-*/policy.xml && rm /tmp/imagemagick-policy.xml

# USER www-data

WORKDIR /app/

# Links the package on ghcr.io to this repository
LABEL org.opencontainers.image.source="https://github.com/ochorocho/typo3-container"

EXPOSE 9000

# Graceful php-fpm shutdown
STOPSIGNAL SIGQUIT

CMD ["/bin/sh", "-c", "echo \"$PHP_CONFIGURATION\" >> /etc/php/$PHP_VERSION/mods-available/typo3.ini && exec php-fpm$PHP_VERSION --allow-to-run-as-root"]
