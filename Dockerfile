FROM php:8.4-cli-bookworm AS vendor

WORKDIR /app

COPY --from=composer:2.8 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libpq-dev \
    && docker-php-ext-install intl \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*
RUN composer install \
    --no-interaction \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json ./
RUN npm install

COPY resources ./resources
COPY public ./public
COPY vite.config.js ./

RUN npm run build

FROM php:8.4-fpm-bookworm AS app

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libonig-dev \
        libxml2-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        pkg-config \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=vendor /app/vendor /var/www/html/vendor
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY . /var/www/html

RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
