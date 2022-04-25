FROM php:8.2-cli-trixie

RUN apt-get update \
  && apt-get install --yes --no-install-recommends \
    git \
    unzip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.11 /usr/bin/composer /usr/bin/composer

COPY composer.json /deps/
RUN composer install --working-dir=/deps --no-interaction --quiet
ENTRYPOINT ["/deps/vendor/bin/php-cs-fixer", "fix", "--config=/app/php-cs-fixer/config.php"]

WORKDIR /app
