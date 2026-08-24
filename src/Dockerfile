# syntax=docker/dockerfile:1.6

ARG PHP_VERSION=8.5

# Composer stage: install PHP dependencies with the same PHP version as runtime
FROM php:${PHP_VERSION}-cli AS vendor
WORKDIR /app
RUN apt-get update && apt-get install -y git unzip zip \
  && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock* ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-ansi \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

# Node stage: build frontend assets with Vite
FROM node:20 AS frontend
WORKDIR /app
COPY package.json package-lock.json* yarn.lock* ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY resources/ ./resources/
COPY vite.config.js ./
COPY public/ ./public/
RUN npm run build

# Final: PHP-FPM runtime
FROM php:${PHP_VERSION}-fpm AS app

# Install system deps and PHP extensions commonly required by Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libxml2-dev \
    libzip-dev \
    libonig-dev \
    zip \
    git \
    curl \
    unzip \
  && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" \
     gd \
     pdo \
     pdo_mysql \
     mbstring \
     exif \
     pcntl \
     bcmath \
     zip \
     xml \
     fileinfo

WORKDIR /var/www/html

# Copy application source
COPY . .

# Copy vendor deps and built assets from previous stages
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/composer.lock ./composer.lock
COPY --from=frontend /app/public/build ./public/build

# Ensure writable dirs for Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Production php.ini tweaks aligned with docs/HOSTING.md
COPY docker/php/conf.d/production.ini /usr/local/etc/php/conf.d/production.ini

# Startup script to optionally warm caches and run migrations
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint.sh
RUN chmod +x /usr/local/bin/app-entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/app-entrypoint.sh"]
CMD ["php-fpm"]

# Nginx image that carries built public assets
FROM nginx:1.25 AS web
COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Default build target remains the PHP-FPM runtime
FROM app AS app-runtime
