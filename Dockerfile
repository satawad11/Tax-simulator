FROM php:8.4-fpm-bookworm AS app
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libonig-dev libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring intl zip bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN touch .env \
    && composer install --no-interaction --prefer-dist \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]

FROM node:24-alpine AS node
WORKDIR /var/www/html
# Playwright ships a glibc-linked Chromium that cannot run on Alpine's musl, so the browser smoke
# (`npm run test:browser`) uses Alpine's own Chromium instead, addressed through
# PLAYWRIGHT_EXECUTABLE_PATH. Without this the release browser check cannot be run at all.
RUN apk add --no-cache chromium nss freetype harfbuzz ttf-freefont
ENV PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 \
    PLAYWRIGHT_EXECUTABLE_PATH=/usr/bin/chromium
COPY package*.json ./
RUN npm ci
COPY . .
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]

FROM nginx:stable-alpine AS nginx
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
