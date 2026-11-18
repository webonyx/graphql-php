ARG IMAGE
FROM ${IMAGE}

# Debian 11 reached end of life and moved to archive.debian.org
RUN if grep -q VERSION_CODENAME=bullseye /etc/os-release; then \
    echo "deb http://archive.debian.org/debian bullseye main" > /etc/apt/sources.list; \
  fi \
  && apt-get update \
  && apt-get install --yes --no-install-recommends \
    git \
    unzip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.11 /usr/bin/composer /usr/bin/composer

COPY composer.json /deps/
RUN composer install --working-dir=/deps --no-interaction --quiet
ENTRYPOINT ["/deps/vendor/bin/php-cs-fixer", "fix", "--config=/app/php-cs-fixer/config.php"]

WORKDIR /app
