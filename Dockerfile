# Spinx — Swoole runtime image
#
# This is the documented deploy path for the opt-in Swoole driver
# (build spec §2.3). Swoole requires a compiled PHP extension that
# doesn't build natively on Windows, so Docker (or WSL2/Linux for local
# dev — see README) is the supported way to run it.
#
# Build: docker build -t spinx-app .
# Run:   docker run -p 9501:9501 spinx-app
#
# IMPORTANT: set "driver": "swoole" in spinx.json before building this
# image (`spinx driver:swap swoole`) — the HTTP server this image runs
# and the database connection pooling strategy the app picks both read
# that same config value, and a mismatch between them is a real, if
# non-fatal, bug (see public/swoole-worker.php's own runtime check).

FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libssl-dev \
        libsqlite3-dev \
    && pecl install swoole \
    && docker-php-ext-enable swoole \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p storage/cache/views storage/frontend \
    && chmod -R 775 storage

EXPOSE 9501

CMD ["php", "public/swoole-worker.php"]
