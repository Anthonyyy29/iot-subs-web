FROM php:8.2-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip git libssl-dev \
    && docker-php-ext-install sockets pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json .
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY bridge.php .

CMD ["php", "bridge.php"]
