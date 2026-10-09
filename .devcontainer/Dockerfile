# The dev container: Debian 13's PHP 8.4, as the application's images run it (Dockerfile-php),
# with Xdebug and composer, all from packages: nothing to compile.
FROM debian:trixie-slim

# The extensions the application and its tests use (GD for the importer's image tests), zip for
# composer, Xdebug for debugging, and the tools around them
RUN apt-get update && apt-get install -y --no-install-recommends \
    php8.4-cli \
    php8.4-gd \
    php8.4-mbstring \
    php8.4-mysql \
    php8.4-xdebug \
    php8.4-xml \
    php8.4-zip \
    ca-certificates \
    curl \
    git \
    mariadb-client \
    unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Xdebug when a request asks for it
RUN printf 'xdebug.mode=develop,debug\nxdebug.client_host=host.docker.internal\nxdebug.start_with_request=trigger\n' \
    > /etc/php/8.4/cli/conf.d/99-xdebug.ini

WORKDIR /workspaces/hhbd
