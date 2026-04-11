FROM php:8.4-fpm-alpine AS base

WORKDIR /var/www/html

RUN apk add --no-cache \
    autoconf \
    curl \
    freetype-dev \
    g++ \
    git \
    gosu \
    icu-data-full \
    icu-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    make \
    oniguruma-dev \
    sqlite-dev \
    unzip \
    wget \
    yt-dlp \
    zip

RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-configure intl --enable-intl \
    && docker-php-ext-install -j$(nproc) \
    bcmath \
    ctype \
    gd \
    intl \
    mbstring \
    opcache \
    pdo \
    pdo_sqlite \
    xml \
    zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY custom-www.conf /usr/local/etc/php-fpm.d/www.conf
COPY src/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

FROM base AS dev

RUN apk add --no-cache \
    nodejs \
    npm \
    pkgconfig

FROM node:24-alpine AS frontend

WORKDIR /app
COPY src/package*.json ./
RUN npm ci
COPY src/ .
RUN npm run build

FROM base AS app

COPY src/ .
COPY --from=frontend /app/public/build /var/www/html/public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html/storage -type d -exec chmod 755 {} \; \
    && find /var/www/html/storage -type f -exec chmod 644 {} \; \
    && find /var/www/html/bootstrap/cache -type d -exec chmod 755 {} \; \
    && find /var/www/html/bootstrap/cache -type f -exec chmod 644 {} \; \
    && mkdir -p /var/www/html/storage/app/public/temp-youtube \
    && chown -R www-data:www-data /var/www/html/storage/app/public/temp-youtube \
    && chmod -R 775 /var/www/html/storage/app/public/temp-youtube

FROM nginx:alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
