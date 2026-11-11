# ─── Backend image: PHP 8.5 CLI + Laravel ───────────────────────────────────
#
# Multi-stage build:
#   1) vendor  — install Composer dependencies (cached unless composer.* change)
#   2) runtime — PHP CLI with the extensions the app needs, app code + vendor
#
# The same image serves two roles in docker-compose: the HTTP API
# (`php artisan serve`) and the queue worker (`php artisan queue:work`). Which
# role a container plays is decided by its compose `command`.

# ---------- stage 1: composer dependencies ----------
FROM composer:2 AS vendor

WORKDIR /app
# Copy only the manifest first so this layer caches across code-only changes.
COPY composer.json composer.lock* ./
# Install without running scripts (artisan isn't present yet) and skip dev deps
# for a lean production vendor tree.
#
# `--ignore-platform-reqs`: the composer image lacks some PHP extensions
# (e.g. ext-gd, needed by phpoffice/phpspreadsheet) that the RUNTIME image
# installs via docker-php-ext-install. We only resolve + download packages
# here, so ignoring platform ext requirements is safe; the extensions are
# present when the app actually runs.
#
# Dev dependencies ARE installed: the database seeders use fakerphp/faker and
# the local provider set references laravel/pail, both `require-dev`. This is a
# local/dev-oriented image, so keeping them makes `db:seed` and `artisan` work
# out of the box. For a hardened production image, drop `--no-dev` back in and
# remove the dev-only seeders/providers.
RUN composer install \
      --no-scripts \
      --no-interaction \
      --prefer-dist \
      --optimize-autoloader \
      --ignore-platform-reqs

# ---------- stage 2: runtime ----------
FROM php:8.5-cli AS runtime

# System libs + PHP extensions required by the app:
#   pdo_mysql  — MySQL (central + tenant databases)
#   bcmath     — decimal money/stock math
#   zip, gd    — PhpSpreadsheet exports
#   pcntl      — queue worker signal handling
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev zlib1g-dev libpng-dev libonig-dev default-mysql-client \
    && docker-php-ext-install pdo_mysql bcmath zip gd pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# App code + installed vendor tree from stage 1.
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Writable runtime directories for the web/queue processes.
RUN chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# The entrypoint waits for MySQL, prepares .env/key/migrations, then execs the
# container command (serve or queue:work).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
