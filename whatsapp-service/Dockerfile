FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    libzip-dev zip unzip curl \
    $PHPIZE_DEPS \
    icu-dev \
    && docker-php-ext-install pdo pdo_mysql opcache intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY . .

RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist \
    && php artisan config:cache || true

EXPOSE 8002

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8002"]
