# =============================================================================
# Development (default) — bind-mount friendly FPM for compose.yml
# =============================================================================
FROM php:8.3-fpm-alpine AS dev

ARG PUID=1000
ARG PGID=1000

RUN docker-php-ext-install pdo_mysql

COPY docker/php/zz-hardening.ini /usr/local/etc/php/conf.d/zz-hardening.ini

RUN deluser www-data \
    && (delgroup www-data || true) \
    && addgroup -g "${PGID}" www-data \
    && adduser -D -H -s /bin/sh -u "${PUID}" -G www-data www-data \
    && mkdir -p /var/www \
    && chown www-data:www-data /var/www

WORKDIR /var/www

# =============================================================================
# Front-end assets
# =============================================================================
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY public ./public
COPY resources ./resources
COPY scripts ./scripts

RUN npm run build

# =============================================================================
# PHP dependencies
# =============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-scripts --no-interaction

# =============================================================================
# Production PHP-FPM
# =============================================================================
FROM php:8.3-fpm-alpine AS php

RUN docker-php-ext-install pdo_mysql \
    && apk add --no-cache fcgi

COPY docker/php/zz-hardening.ini /usr/local/etc/php/conf.d/zz-hardening.ini
COPY docker/php/zz-opcache-prod.ini /usr/local/etc/php/conf.d/zz-opcache-prod.ini
COPY docker/php/zz-fpm-ping.conf /usr/local/etc/php-fpm.d/zz-ping.conf

WORKDIR /var/www

COPY --from=vendor /app/vendor ./vendor
COPY src ./src
COPY themes ./themes
COPY lang ./lang
COPY bin ./bin
COPY --from=assets /app/public ./public

RUN mkdir -p var/sessions var/rate-limit var/cache var/uploads/tickets var/downloads var/backups public/uploads \
    && chown -R www-data:www-data var public/uploads

USER www-data

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET \
        cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

# =============================================================================
# Production Nginx
# =============================================================================
FROM nginx:alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=assets /app/public /var/www/public

RUN mkdir -p /var/www/public/uploads

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD wget -qO- http://127.0.0.1/health || exit 1
