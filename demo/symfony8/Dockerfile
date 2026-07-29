# FrankenPHP + PHP 8.5 for Password Policy Bundle Demo (Symfony 8)
# https://frankenphp.dev/docs/docker/
FROM dunglas/frankenphp:1-php8.5-alpine

RUN apk add --no-cache git unzip bash curl ca-certificates

RUN install-php-extensions zip pdo_mysql intl xdebug

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory /app && \
    git config --global --add safe.directory /var/password-policy-bundle

WORKDIR /app

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PATH="/app/vendor/bin:${PATH}"
ENV SERVER_NAME=:80
# Xdebug: set XDEBUG_MODE=debug in .env or docker-compose to enable step debugging
ENV XDEBUG_MODE=develop
ENV XDEBUG_CONFIG="client_host=host.docker.internal client_port=9003"

COPY docker/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY docker/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile.dev

COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
