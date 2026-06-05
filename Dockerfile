FROM php:8.4-cli-alpine

RUN apk add --no-cache icu-dev icu-libs \
    && docker-php-ext-install pdo_mysql intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
CMD ["sleep", "infinity"]
