FROM php:8.1-cli-alpine

RUN apk add --no-cache \
    git curl unzip \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd pdo_mysql mysqli mbstring zip intl opcache

COPY php.ini /usr/local/etc/php/conf.d/webasyst.ini
COPY entrypoint.sh /entrypoint.sh
COPY router.php /router.php
RUN chmod +x /entrypoint.sh

WORKDIR /webasyst
ENTRYPOINT ["/entrypoint.sh"]
