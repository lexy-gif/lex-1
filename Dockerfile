FROM php:8.4-apache

RUN docker-php-ext-install pdo_mysql

COPY deploy/apache-srms.conf /etc/apache2/conf-available/srms.conf
COPY deploy/php-production.ini /usr/local/etc/php/conf.d/srms.ini
RUN a2enconf srms && a2enmod headers

WORKDIR /var/www/html

COPY . /var/www/html/
