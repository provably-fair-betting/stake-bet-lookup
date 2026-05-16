# Self-contained image — no local app/ directory required, no secrets baked in.
# All sensitive values (APP_KEY, STAKE_ADMIN_TOKEN) are runtime environment variables.
#
# Builds the Laravel application from scratch inside Docker using the
# stake/bet-lookup Composer package bundled alongside this Dockerfile.

FROM php:8.2-fpm

# ── System dependencies ────────────────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
        git curl libpng-dev libonig-dev libxml2-dev zip unzip \
        nginx supervisor \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Avoid OOM during composer install
ENV COMPOSER_MEMORY_LIMIT=-1

# ── Package source ─────────────────────────────────────────────────────────────
COPY . /var/laravel-package/

# ── Bootstrap the Laravel application ─────────────────────────────────────────
# --no-install defers the install until after the path repository is configured,
# so everything resolves in a single composer pass.
# WORKDIR / avoids deleting the shell's own CWD (php:8.2-fpm defaults to /var/www/html).
WORKDIR /
RUN rm -rf /var/www/html \
    && composer create-project laravel/laravel:^12.0 /var/www \
        --prefer-dist --no-install --no-scripts \
    && cd /var/www \
    && composer config platform.php 8.2 \
    && composer config repositories.bet-lookup \
        '{"type":"path","url":"/var/laravel-package","options":{"symlink":false}}' \
    && composer require stake/bet-lookup \
    && php artisan package:discover --ansi

# ── Publish config and migrations ─────────────────────────────────────────────
RUN cd /var/www \
    && php artisan vendor:publish --tag=bet-lookup-config --quiet \
    && php artisan vendor:publish --tag=bet-lookup-migrations --quiet

# ── Strip unused Laravel scaffolding ──────────────────────────────────────────
RUN echo "<?php" > /var/www/routes/web.php \
    && rm -rf /var/www/resources/js \
              /var/www/resources/css \
              /var/www/resources/views \
    && rm -f  /var/www/package.json \
              /var/www/vite.config.js \
              /var/www/.editorconfig

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# ── nginx ──────────────────────────────────────────────────────────────────────
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf /etc/nginx/sites-enabled/default

# ── supervisor ─────────────────────────────────────────────────────────────────
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Entrypoint ─────────────────────────────────────────────────────────────────
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
