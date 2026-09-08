FROM php:8.3-fpm-alpine

ARG PUID=1000
ARG PGID=1000

RUN docker-php-ext-install pdo_mysql

# Workers run as www-data. Match the bind-mount owner so /setup can write .env.
RUN deluser www-data \
    && (delgroup www-data || true) \
    && addgroup -g "${PGID}" www-data \
    && adduser -D -H -s /bin/sh -u "${PUID}" -G www-data www-data \
    && mkdir -p /var/www \
    && chown www-data:www-data /var/www

WORKDIR /var/www
