ARG PHP_VERSION=8.3

FROM php:${PHP_VERSION}-cli-alpine

RUN mv $PHP_INI_DIR/php.ini-development $PHP_INI_DIR/php.ini

RUN apk update && \
    apk add oniguruma-dev && \
    apk add libpq-dev && \
    docker-php-ext-install -j$(nproc) mbstring pdo pdo_mysql pgsql pdo_pgsql

# SQL Server (inspired from https://github.com/kool-dev/docker-php-sqlsrv/blob/main/7.4-nginx-sqlsrv/Dockerfile)
RUN curl -O https://download.microsoft.com/download/fae28b9a-d880-42fd-9b98-d779f0fdd77f/msodbcsql18_18.5.1.1-1_amd64.apk && \
    curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/mssql-tools18_18.4.1.1-1_amd64.apk && \
    apk add --allow-untrusted msodbcsql18_18.5.1.1-1_amd64.apk && \
    apk add --allow-untrusted mssql-tools18_18.4.1.1-1_amd64.apk && \
    apk add --no-cache --virtual .persistent-deps freetds unixodbc && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS freetds-dev unixodbc-dev && \
    docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr && \
    docker-php-ext-install pdo_odbc && \
    pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv || \
    echo "Can't install ODBC drivers"

RUN apk add linux-headers && \
    apk add $PHPIZE_DEPS && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug || \
    echo "Can't install XDEBUG"

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/

ENV XDEBUG_MODE=coverage

ARG UID=1000
ARG GID=1000
USER ${UID}:${GID}

WORKDIR /app
