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

COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer

COPY composer.json /tmp/
RUN VERSION=$(grep -oP '"friendsofphp/php-cs-fixer":\s*"\K[^"]+' /tmp/composer.json) \
  && MLL_VERSION=$(grep -oP '"mll-lab/php-cs-fixer-config":\s*"\K[^"]+' /tmp/composer.json) \
  && mkdir /deps \
  && composer require --working-dir=/deps --no-interaction --quiet \
    "friendsofphp/php-cs-fixer:$VERSION" \
    "mll-lab/php-cs-fixer-config:$MLL_VERSION"
ENTRYPOINT ["/deps/vendor/bin/php-cs-fixer", "fix", "--config=/app/.php-cs-fixer.php"]

WORKDIR /app
