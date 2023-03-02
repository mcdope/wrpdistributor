# Image and version
FROM php:8.2-fpm
ARG DEBIAN_FRONTEND=noninteractive

# Prepare APT
RUN apt-get -y update --fix-missing && apt-get upgrade -y

# Install tools and requirements
RUN apt-get -y --no-install-recommends install build-essential curl git openssh-client openssl libsodium-dev libgmp-dev libxml2-dev libzip-dev

# Install xdebug
RUN pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    mkdir /var/log/xdebug && \
    echo "xdebug.mode=coverage" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install sodium
RUN docker-php-ext-install gmp
RUN docker-php-ext-install zip
RUN docker-php-ext-install dom

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Cleanup
RUN apt-get -y autoremove && apt-get -y autoclean
RUN rm -rf /usr/src/* /tmp/* /var/lib/apt/lists/*