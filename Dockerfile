# Multi-stage build — builder compiles PHP extensions and installs the Laravel
# app; the runtime stage copies only what is needed to run, keeping the final
# image lean (~350-400 MB vs ~1 GB single-stage Debian).
#
# All sensitive values (APP_KEY, STAKE_ADMIN_TOKEN) are runtime environment
# variables — nothing secret is baked into the image.

# ── Builder ───────────────────────────────────────────────────────────────────
FROM php:8.2-fpm-alpine AS builder

# Build-time only: headers for PHP extension compilation + Composer tools
RUN apk add --no-cache \
        git curl zip unzip \
        libpng-dev oniguruma-dev libxml2-dev

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1

# ── Scaffold Laravel (cached until laravel/laravel:^12.0 bumps) ───────────────
# --no-install defers composer install until after the path repository is
# configured, so everything resolves in a single pass.
# WORKDIR / avoids deleting the shell's own CWD (php:8.2-fpm defaults to /var/www/html).
WORKDIR /
RUN rm -rf /var/www/html \
    && composer create-project laravel/laravel:^12.0 /var/www \
        --prefer-dist --no-install --no-scripts \
    && cd /var/www \
    && composer config platform.php 8.2

# ── Package source (invalidates cache from here on code changes) ──────────────
COPY . /var/laravel-package/

# ── Install package into Laravel ──────────────────────────────────────────────
# --no-install defers the actual package installation so a single composer
# install pass strips dev deps and optimises the autoloader in one step.
# --no-scripts on both commands skips Laravel's post-update/install hooks
# (vendor:publish laravel-assets) which need vendor/autoload.php to exist;
# package:discover runs manually afterwards as the only hook we need.
RUN cd /var/www \
    && composer config repositories.bet-lookup \
        '{"type":"path","url":"/var/laravel-package","options":{"symlink":false}}' \
    && composer require stake/bet-lookup --no-install --no-scripts \
    && composer install --no-dev --optimize-autoloader --no-scripts \
    && php artisan package:discover --ansi

RUN cd /var/www \
    && php artisan vendor:publish --tag=bet-lookup-config --quiet \
    && php artisan vendor:publish --tag=bet-lookup-migrations --quiet

# Strip unused Laravel scaffolding
RUN echo "<?php" > /var/www/routes/web.php \
    && rm -rf /var/www/resources/js \
              /var/www/resources/css \
              /var/www/resources/views \
    && rm -f  /var/www/package.json \
              /var/www/vite.config.js \
              /var/www/.editorconfig

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# ── Runtime ───────────────────────────────────────────────────────────────────
FROM php:8.2-fpm-alpine AS runtime

# Runtime shared libraries — no headers, no build tools, no composer
RUN apk add --no-cache \
        nginx supervisor \
        libpng oniguruma libxml2

# PHP extensions compiled in the builder stage
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d     /usr/local/etc/php/conf.d

# Application
COPY --from=builder /var/www /var/www

# Alpine nginx serves from /etc/nginx/http.d/ (not sites-enabled)
COPY docker/nginx.conf       /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
