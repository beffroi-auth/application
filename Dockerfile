# syntax=docker/dockerfile:1

# Each base image names its version and is pinned by the digest of that tag, so that a build of a commit takes the
# PHP, FrankenPHP and Debian that commit names. Dependabot proposes a release of FrankenPHP or a point release of
# Debian as a new tag, and a rebuild of the same tag (FrankenPHP's for a patch of PHP, Debian's for a security
# update) as a new digest (issue #1216).
FROM dunglas/frankenphp:1.14.0-php8.5-trixie@sha256:0b21a2960d1a2e146d283f1e3ee7ef64b3c27781e372f13457f1c41d2419be75 AS frankenphp_base

WORKDIR /app

RUN install-php-extensions \
    @composer \
    apcu \
    intl \
    opcache \
    zip \
    pdo_pgsql \
    gmp \
    sodium \
    uuid

# gpg reads the OpenPGP key a person gives and encrypts the mail written to them (decision 0051); it starts no agent,
# which would hold a key in a process of its own and that the provider never needs, a public key being all it hands gpg.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends gpg; \
    rm -rf /var/lib/apt/lists/*
COPY --link apps/beffroi/frankenphp/gnupg/gpg.conf /etc/gnupg/gpg.conf

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link apps/beffroi/frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 apps/beffroi/frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link --chmod=755 apps/beffroi/frankenphp/docker-healthcheck.php /usr/local/bin/docker-healthcheck
COPY --link --chmod=755 apps/beffroi/frankenphp/docker-request-bounds.sh /usr/local/bin/docker-request-bounds
COPY --link apps/beffroi/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
COPY --link apps/beffroi/frankenphp/worker.Caddyfile /etc/frankenphp/worker.Caddyfile
COPY --link apps/beffroi/frankenphp/mtls.Caddyfile /etc/frankenphp/mtls.Caddyfile
COPY --link apps/beffroi/frankenphp/administration.Caddyfile /etc/frankenphp/administration.Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# Development image: the monorepo is bind-mounted, the packages are symlinked into vendor/ by Composer.
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
ENV XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

COPY --link apps/beffroi/frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/app.conf.d/

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--watch" ]

# Production image, in two stages: this one installs and builds the application where the extensions are
# compiled, and the next one is what image.yml publishes and a deployment runs. The packages and the
# application are copied so that the path repositories resolve, then the application is installed.
FROM frankenphp_base AS frankenphp_prod_builder

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link apps/beffroi/frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Build context is the monorepo root (see compose.yaml), which Dockerfile.dockerignore narrows to the
# packages and the application without what a working copy holds beside the repository: its .env.local,
# var/ and vendor/ never reach an image. Nor does what only the development stack, the suite and the lab
# read (issue #922). The packages land in /src so that the path repositories of composer.json
# (../../src/<package>, relative to /app) resolve unchanged. The files of FrankenPHP and of PHP are copied
# where they are read by the stages above, the configuration of the development image among them, which is
# why the context keeps them and the application leaves its frankenphp/ out of /app.
COPY --link src/ /src/
COPY --link --exclude=frankenphp apps/beffroi/ /app/

# The standalone binary of Tailwind goes once the stylesheet it built is compiled into public/assets. The
# directories the processes write are made here, the volumes of compose.prod.yaml among them, since a named
# volume takes the owner of the directory it is first mounted on.
RUN set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    chmod +x bin/console; sync; \
    bin/console tailwind:build --minify --no-debug; \
    bin/console asset-map:compile --no-debug; \
    rm -rf var/tailwind/v*; \
    mkdir -p var/keys var/kms var/geoip var/webauthn var/share var/log

# The shared libraries FrankenPHP, PHP and each extension load, collected for a runtime that installs none.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libtree; \
    mkdir /tmp/libs; \
    for target in /usr/local/bin/frankenphp /usr/local/bin/php $(find "$(php -r 'echo ini_get("extension_dir");')" -name '*.so'); do \
        libtree -pv "$target" | grep -oP '(?:── )\K/\S+(?= \[)' | while read -r library; do \
            cp -n "$library" /tmp/libs/; \
        done; \
    done; \
    rm -rf /var/lib/apt/lists/*

# The production image: Debian slim, of the release the FrankenPHP image is built on, with FrankenPHP, PHP,
# the extensions and the libraries they load, the packages and the application, and nothing to compile,
# install or download with (issue #932). Every process runs as www-data, named by its number so that a
# runtime can tell it is not root: the code is root's and only read, var/ and what Caddy keeps are www-data's,
# group 0 holding what the owner holds for a runtime that draws the uid itself.
FROM debian:13.7-slim@sha256:a72b4433de1cf7d6bae0c4cee74d0547f58601a643b3375f070c51600d63b6b2 AS frankenphp_prod

ENV APP_ENV=prod
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"
ENV PHP_INI_SCAN_DIR=":/usr/local/etc/php/app.conf.d"
ENV XDG_CONFIG_HOME=/config XDG_DATA_HOME=/data GODEBUG=cgocheck=0
ENV SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt OPENSSL_CONF=/etc/ssl/openssl.cnf

COPY --link --from=frankenphp_prod_builder /usr/local/bin/frankenphp /usr/local/bin/php /usr/local/bin/docker-php-entrypoint /usr/local/bin/docker-entrypoint /usr/local/bin/docker-healthcheck /usr/local/bin/docker-request-bounds /usr/local/bin/
COPY --link --from=frankenphp_prod_builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --link --from=frankenphp_prod_builder /usr/local/etc/php /usr/local/etc/php
COPY --link --from=frankenphp_prod_builder /tmp/libs /usr/lib/
COPY --link --from=frankenphp_prod_builder /etc/frankenphp /etc/frankenphp
COPY --link --from=frankenphp_prod_builder /etc/ssl/certs/ca-certificates.crt /etc/ssl/certs/
COPY --link --from=frankenphp_prod_builder /etc/ssl/openssl.cnf /etc/ssl/
COPY --link --from=frankenphp_prod_builder /etc/gnupg/gpg.conf /etc/gnupg/gpg.conf

# gpg and the libraries it loads, the one program of Debian the application runs (decision 0051), installed from the
# archive of the release the image is built on and nothing else with it.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends gpg; \
    rm -rf /var/lib/apt/lists/* /var/cache/debconf/*-old /var/log/apt /var/log/dpkg.log; \
    gpg --version > /dev/null
COPY --link --from=frankenphp_prod_builder /src /src
COPY --link --exclude=var --from=frankenphp_prod_builder /app /app
# Without --link, which would give www-data the /app this layer creates: it writes in var/ and nowhere above.
COPY --chown=33:0 --from=frankenphp_prod_builder /app/var /app/var

# The setuid and setgid bits of the system go, and the build fails here rather than at the first start when
# FrankenPHP or an extension misses a library, or when Caddy refuses its configuration in one of the two ways
# the image serves the site of SERVER_NAME: Caddy terminating TLS, and a proxy in front of it terminating TLS
# (TLS_TERMINATION, issue #908). Behind such a proxy, the site of the administration API is adapted too, and
# must carry the directives a deployment gives it (CADDY_ADMINISTRATION_EXTRA_DIRECTIVES, issue #1042); each
# listener carries the options a deployment gives them all (CADDY_SERVER_OPTIONS) and bounds the headers of a
# request, and each site its body (issue #1191); docker-request-bounds passes a size of 1B and one of 7EiB, and
# stops on a size of each of their variables that bounds nothing (issue #1198).
# The configuration is adapted and not validated: validating provisions it, which draws the key of the internal
# CA of Caddy into /data, and every deployment of the image would start with the same one.
RUN set -eux; \
    mkdir -p /data/caddy /config/caddy; \
    chown -R 33:0 /data /config; \
    chmod -R g=u /app/var /data /config; \
    find / -xdev -perm /6000 -type f -exec chmod a-s {} +; \
    frankenphp version; \
    frankenphp adapt --config /etc/frankenphp/Caddyfile > /dev/null; \
    TLS_TERMINATION=proxy SERVER_NAME=:80 frankenphp adapt --config /etc/frankenphp/Caddyfile > /dev/null; \
    behind_a_proxy="$(TLS_TERMINATION=proxy SERVER_NAME=:80 ADMINISTRATION_SERVER_NAME=:81 \
        CADDY_EXTRA_CONFIG='import administration.Caddyfile' \
        CADDY_ADMINISTRATION_EXTRA_DIRECTIVES='request_header X-Forwarded-For {client_ip}' \
        CADDY_SERVER_OPTIONS='trusted_proxies static private_ranges' \
        frankenphp adapt --config /etc/frankenphp/Caddyfile)"; \
    echo "${behind_a_proxy}" | grep -q '"X-Forwarded-For"'; \
    test "$(echo "${behind_a_proxy}" | grep -o '"trusted_proxies"' | wc -l)" -eq 2; \
    test "$(echo "${behind_a_proxy}" | grep -o '"max_header_bytes":16384' | wc -l)" -eq 2; \
    echo "${behind_a_proxy}" | grep -q '"max_size":2097152'; \
    echo "${behind_a_proxy}" | grep -q '"max_size":262144'; \
    CADDY_MAX_HEADER_SIZE=1B CADDY_REQUEST_BODY_MAX_SIZE=7EiB docker-request-bounds; \
    for nothing in CADDY_MAX_HEADER_SIZE=0KiB CADDY_REQUEST_BODY_MAX_SIZE=0.4B CADDY_ADMINISTRATION_REQUEST_BODY_MAX_SIZE=8EiB; do \
        if env "${nothing}" docker-request-bounds; then exit 1; fi; \
    done; \
    test -z "$(php -m 2>&1 >/dev/null)"

USER 33
WORKDIR /app

ENTRYPOINT ["docker-entrypoint"]

# Healthy when the provider answers through the site of SERVER_NAME, and not when Caddy alone does: a configuration the
# kernel refuses leaves every worker ready and fails each request (issue #1192).
HEALTHCHECK --start-period=60s CMD ["docker-healthcheck"]
CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]
