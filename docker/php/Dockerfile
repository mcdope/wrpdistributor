# Image and version
FROM php:8.2-fpm
ARG DEBIAN_FRONTEND=noninteractive

RUN apt-get -y update --fix-missing && \
    apt-get upgrade -y && \
    apt-get -y --no-install-recommends install build-essential curl cron git openssh-client openssl unzip libsodium-dev libgmp-dev libxml2-dev libzip-dev && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    mkdir /var/log/xdebug && \
    echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    docker-php-ext-install opcache && \
    docker-php-ext-install pdo_mysql && \
    docker-php-ext-install sodium && \
    docker-php-ext-install gmp && \
    docker-php-ext-install zip && \
    docker-php-ext-install dom && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    apt-get -y autoremove && apt-get -y autoclean && \
    rm -rf /usr/src/* /tmp/* /var/lib/apt/lists/* && \
    crontab -l | { cat; echo "* * * * * /var/www/bin/crons.sh"; } | crontab -

CMD cron && php-fpm
