# ---- stage: vendor (instala dependencias sin correr scripts de composer) ----
FROM composer:2.8.4 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Evita llamar a "artisan" en este stage
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --optimize-autoloader

# ---- stage: runtime (php-fpm) ----
FROM php:8.2-fpm-alpine

# Extensiones necesarias
RUN apk add --no-cache icu-dev oniguruma-dev libpng-dev libjpeg-turbo-dev libwebp-dev libzip-dev freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j$(nproc) gd intl pdo pdo_mysql zip bcmath opcache

# Configurar PHP para manejar archivos grandes
RUN echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# Copiá todo el código y luego vendor desde el stage vendor
COPY . ./
COPY --from=vendor /app/vendor ./vendor

# Permisos de Laravel
RUN chown -R www-data:www-data storage bootstrap/cache \
    && find storage -type d -exec chmod 775 {} \; \
    && find storage -type f -exec chmod 664 {} \; \
    && chmod -R 775 bootstrap/cache

# (Opcional) NO correr artisan en build: en CI/CD no tenés APP_KEY/DB, fallaría.
# Si querés, podés habilitarlo cuando tengas variables listas:
# RUN php artisan package:discover --ansi || true \
#  && php artisan config:cache || true \
#  && php artisan route:cache  || true \
#  && php artisan view:cache   || true

EXPOSE 9000
CMD ["php-fpm", "-F"]
