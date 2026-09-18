# syntax=docker/dockerfile:1
ARG PHP_VERSION=8.5

FROM dunglas/frankenphp:1-php${PHP_VERSION} AS frankenphp_base

WORKDIR /app

RUN install-php-extensions \
    @composer \
    apcu \
    intl \
    opcache \
    zip \
    pdo_pgsql \
    amqp \
    gmp \
    sodium \
    uuid

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link apps/op/frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 apps/op/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link apps/op/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --link apps/op/frankenphp/worker.Caddyfile /etc/frankenphp/worker.Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# Development image: the monorepo is bind-mounted, the packages are symlinked into vendor/ by Composer.
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --link apps/op/frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

# Production image: the whole monorepo is copied so that the path repositories resolve, then the application is installed.
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link apps/op/frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Build context is the monorepo root (see compose.yaml). The packages land in /src so that the
# path repositories of composer.json (../../src/<package>, relative to /app) resolve unchanged.
COPY --link src/ /src/
COPY --link apps/op/ /app/

RUN set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    chmod +x bin/console; sync; \
    bin/console tailwind:build --minify --no-debug; \
    bin/console asset-map:compile --no-debug;
