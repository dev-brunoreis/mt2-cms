FROM php:8.3-fpm-alpine

ARG PUID=1000
ARG PGID=1000

RUN docker-php-ext-install pdo_mysql \
    && deluser www-data 2>/dev/null || true \
    && addgroup -g "${PGID}" www-data \
    && adduser -u "${PUID}" -G www-data -s /bin/sh -D www-data \
    && mkdir -p /var/www \
    && chown www-data:www-data /var/www

WORKDIR /var/www

USER www-data
