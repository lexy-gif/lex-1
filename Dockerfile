FROM composer:2 AS composer

FROM php:8.4-apache AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY deploy/apache-srms.conf /etc/apache2/conf-available/srms.conf
COPY deploy/php-production.ini /usr/local/etc/php/conf.d/srms.ini
RUN a2enconf srms && a2enmod headers

WORKDIR /var/www/html

FROM php-base AS app

COPY composer.json composer.lock ./

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-plugins \
        --no-scripts \
    && composer check-platform-reqs

COPY . /var/www/html/
