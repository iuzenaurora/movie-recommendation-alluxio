FROM php:8.3-cli-bookworm

# System deps + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libonig-dev libxml2-dev libsqlite3-dev \
    && docker-php-ext-install mbstring zip pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

# Tách riêng dependencies để cache Docker layer
COPY web/composer.json web/composer.lock ./
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev --no-scripts

# Copy toàn bộ mã nguồn Laravel
COPY web/ /var/www/app/

# Khởi tạo .env, database sqlite, storage và app key
RUN if [ ! -f .env ]; then cp .env.example .env; fi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache database \
    && touch database/database.sqlite \
    && chmod -R 777 storage bootstrap/cache database \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && php artisan key:generate --force \
    && php artisan config:clear

ENV APP_ENV=local \
    APP_DEBUG=false \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    LOG_CHANNEL=stderr

# Khởi động container: tự động tạo thư mục session/storage nếu volume được mount và chạy migrations
CMD ["sh", "/var/www/app/start.sh"]
