FROM php:8.3-apache

LABEL org.opencontainers.image.title="askbot_php"
LABEL org.opencontainers.image.source="https://github.com/andreaskasper/askbot_php"

COPY ./php.ini /usr/local/etc/php/php.ini
COPY ./apache-vhost.conf /etc/apache2/sites-available/000-default.conf

RUN apt-get update -y \
    && apt-get install -y --no-install-recommends \
        git unzip zlib1g-dev libzip-dev libjpeg-dev libpng-dev libwebp-dev libicu-dev \
    && docker-php-ext-configure gd --with-webp --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli bcmath gd zip intl opcache \
    && pecl install apcu && docker-php-ext-enable apcu \
    && a2enmod headers rewrite expires \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
