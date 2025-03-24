ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-cli-alpine

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

RUN apk update && \
    apk add oniguruma-dev && \
    docker-php-ext-install mbstring pdo pdo_mysql

# RUN apk add linux-headers && \
#     apk add $PHPIZE_DEPS && \
#     pecl install xdebug && \
#     docker-php-ext-enable xdebug || \
#     echo "Can't install XDEBUG"

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/

ENV XDEBUG_MODE=coverage

ARG UID=1000
ARG GID=1000
USER ${UID}:${GID}

WORKDIR /app
