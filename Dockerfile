ARG PHP_VERSION '8.3'

FROM php:${PHP_VERSION}-fpm-alpine

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions curl mbstring zip pcntl pdo pdo_sqlite iconv pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/

WORKDIR /app

COPY . /app

RUN touch .env \
  && echo "DB_HOST=mysql"      >> .env \
  && echo "DB_PORT=3306"       >> .env \
  && echo "DB_DATABASE=sqlout" >> .env \
  && echo "DB_USERNAME=lara"   >> .env \
  && echo "DB_PASSWORD=lara"   >> .env
