# FrankenPHP (Dunglas) - PHP 8.4 Alpine (§2.4 BUNDLES_STANDARDS_PROMPT)
# Demo dependencies (composer.lock) require PHP >= 8.4; use 8.4 image to match.
# https://frankenphp.dev/docs/docker/
FROM dunglas/frankenphp:1-php8.4-alpine

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

RUN echo '#!/bin/sh' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ "${APP_ENV:-prod}" = "dev" ]; then cp /etc/frankenphp/Caddyfile.dev /etc/frankenphp/Caddyfile; fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'mkdir -p /app/var/cache /app/var/log' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod -R 777 /app/var' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ -d /app/vendor ] && [ "${APP_ENV:-dev}" = dev ]; then (cd /app && php bin/console cache:clear) 2>/dev/null || true; fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec frankenphp run --config /etc/frankenphp/Caddyfile --adapter caddyfile' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
