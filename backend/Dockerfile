FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev libicu-dev gdal-bin postgresql-client unzip \
    && docker-php-ext-install pdo pdo_pgsql zip intl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .
RUN composer dump-autoload --optimize \
    && chmod +x bin/console \
    && mkdir -p var/cache var/log \
    && mkdir -p public/bundles \
    && cp -r vendor/api-platform/core/src/Symfony/Bundle/Resources/public public/bundles/apiplatform \
    && chown -R www-data:www-data var

# Apache config
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && a2enmod rewrite

EXPOSE 80
