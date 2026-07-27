FROM php:8.4-cli

# System deps + PHP extensions Laravel/SQLite need
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libsqlite3-dev libicu-dev \
    && docker-php-ext-install pdo pdo_sqlite bcmath zip intl \
    && rm -rf /var/lib/apt/lists/*

# Composer from the official image
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

EXPOSE 8000

# Dev server; bound to 0.0.0.0 so it's reachable from the host (and ngrok)
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
