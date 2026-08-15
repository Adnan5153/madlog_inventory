# =============================================================================
# Madlog Inventory — Render Docker image
# =============================================================================
# Multi-stage build:
#   vendor   : composer install (PHP deps)  — so the frontend can resolve
#              `vendor/livewire/flux/dist/flux.css` while running Vite
#   frontend : npm build (Vite 8 needs Node 20.19+)
#   backend  : php-fpm + nginx + supervisord, exposing HTTP on $PORT
#              (Render assigns $PORT and expects an HTTP listener)
# =============================================================================

# ---- Stage 1: PHP dependencies ---------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# ---- Stage 2: Frontend build (Vite) ----------------------------------------
FROM node:22 AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
# Vite's @tailwindcss/vite plugin resolves files inside vendor/ (e.g.
# `vendor/livewire/flux/dist/flux.css`), so vendor/ must exist before build.
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---- Stage 3: Runtime (php-fpm + nginx + supervisord) ---------------------
FROM php:8.4-fpm AS backend

# System dependencies: nginx, supervisor, plus the bits php needs.
# `libpq-dev` is needed to build pdo_pgsql + pgsql extensions.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git curl unzip libpq-dev libonig-dev libzip-dev zip \
        nginx supervisor \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql mbstring zip \
    && rm -rf /var/lib/apt/lists/*

# Composer (used at build time to install PHP deps in this stage)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy application code
COPY . .

# Copy built frontend assets (Vite writes to public/build/ by default)
COPY --from=frontend /app/public/build ./public/build

# Install PHP dependencies (no-dev, optimized)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Laravel caches (cleared so the first boot picks up env vars provided at runtime)
RUN php artisan config:clear && \
    php artisan route:clear && \
    php artisan view:clear && \
    php artisan storage:link || true

# Copy nginx + supervisord + entrypoint configs
COPY docker/nginx.conf       /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Render injects $PORT (defaults to 10000). Ensure nginx listens on it.
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
