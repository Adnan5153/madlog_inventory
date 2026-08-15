# Stage 1 - Build assets
# `tailwaindcss/vite` plugin needs to resolve files inside `vendor/`
# (e.g. `vendor/livewire/flux/dist/flux.css`), so we install PHP
# dependencies BEFORE running the frontend build.

# ---- Composer stage (so vendor/ is available to the frontend build) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# ---- Frontend build stage ----
# Vite 8 / rolldown require Node.js >= 20.19 (styleText export from node:util)
FROM node:22 AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
# Pull in vendor/ from the previous stage so flux.css and other
# `../../vendor/...` imports inside our CSS resolve correctly.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# Stage 2 - Backend (Laravel + PHP + Composer)
FROM php:8.4-fpm AS backend

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libonig-dev libzip-dev zip \
    && docker-php-ext-install pdo pdo_mysql mbstring zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app files
COPY . .

# Copy built frontend from the frontend stage
# Laravel's vite-plugin emits assets to public/build by default
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies (no-dev, optimized autoloader)
RUN composer install --no-dev --optimize-autoloader

# Laravel setup
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear

CMD ["php-fpm"]
