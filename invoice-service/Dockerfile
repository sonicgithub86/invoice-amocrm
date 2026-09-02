FROM php:8.3-apache-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends libreoffice-writer poppler-utils libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev libpq-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd intl pdo_pgsql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/invoice-service
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
COPY . .
COPY deploy/php.ini /usr/local/etc/php/conf.d/invoice-service.ini
RUN chown -R www-data:www-data /var/www/invoice-service \
    && mkdir -p /var/lib/invoice-service/documents \
    && chown -R www-data:www-data /var/lib/invoice-service \
    && chmod +x bin/console

ENV APACHE_DOCUMENT_ROOT=/var/www/invoice-service/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
