# Dev image for Expense Tracker (Laravel 12 + Inertia/Vue 3).
# Plain php:8.4-cli has no pdo_pgsql, which Laravel needs for DB_CONNECTION=pgsql.
FROM php:8.4-cli

# Node is needed in-image because the Vite build runs `php artisan
# wayfinder:generate`, so PHP and Node must live in the same container.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev unzip git curl ca-certificates \
    && docker-php-ext-install pdo_pgsql pgsql \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
