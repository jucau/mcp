FROM php:8.5-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions \
# pcov: line-coverage driver Infection needs for its initial pass; near-zero overhead otherwise.
 && install-php-extensions pdo_pgsql mbstring bcmath opcache pcntl intl zip pcov

WORKDIR /var/www/html
