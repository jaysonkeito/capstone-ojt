# |--------------------------------------------------------------------------
# | OJT Tracker production image
# |--------------------------------------------------------------------------
# |
# | Three stages:
# |   assets  — builds the Vite bundle (CSS + the offline client layer)
# |   vendor  — Composer dependencies, production-only
# |   runtime — php:8.4-fpm with the extensions Laravel + the QR renderer need
# |
# | The running stack is described in docker-compose.yml: this image serves
# | php-fpm (app), the queue worker, and the scheduler; Nginx sits in front
# | as the `web` service.
# |
# | NOTE: PHP 8.4 is the minimum — the locked Symfony 8.x components require
# | >= 8.4.1 (the dev machine runs 8.5; don't downgrade this image to 8.3).

# ---------------------------------------------------------------- assets build
FROM node:22-alpine AS assets

WORKDIR /build
COPY package.json package-lock.json ./
# sharp's native binary ships as an npm package (see package.json overrides),
# so the repo's ignore-scripts setting is safe here.
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ------------------------------------------------------------ composer install
FROM composer:2 AS vendor

# The composer image is a bare PHP CLI — phpWord needs ext-gd and friends at
# install time (composer validates platform requirements), so give it the
# same extensions the runtime image carries.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql mbstring gd intl bcmath zip

WORKDIR /app
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# -------------------------------------------------------------------- runtime
FROM php:8.4-fpm-bookworm

# pdo_mysql/mbstring/gd/intl/bcmath/zip for the framework, opcache for speed,
# pcntl for the queue worker. GD renders the interns' QR codes.
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql mbstring gd intl bcmath zip opcache pcntl

# Production PHP settings (uploads, opcache, timezone) — see docker/php/app.ini.
COPY docker/php/app.ini /usr/local/etc/php/conf.d/zz-ojt-app.ini

WORKDIR /var/www
COPY --from=vendor /app/vendor ./vendor
COPY . .
COPY --from=assets /build/public/build ./public/build

# Pristine copy of public/ (including the freshly built assets) — the
# entrypoint seeds the shared public volume from this on every boot, so
# Nginx always serves the current build.
RUN mkdir -p /opt && cp -a /var/www/public /opt/public-src

# The entrypoint may be edited on Windows hosts; strip any CR characters so
# the shell script survives a working-copy round-trip.
COPY docker/app/entrypoint.sh /entrypoint.sh
RUN sed -i 's/\r$//' /entrypoint.sh && chmod +x /entrypoint.sh \
    && mkdir -p storage/framework/{cache/data,sessions,testing,views} \
    && chmod -R ug+rwX storage bootstrap/cache

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
