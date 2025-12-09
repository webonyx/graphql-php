ARG PHP_VERSION
FROM php:${PHP_VERSION}-cli

RUN apt-get update \
  && apt-get install --yes --no-install-recommends \
    git \
    unzip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

COPY composer.json /deps/
RUN composer update --working-dir=/deps --no-interaction --quiet
ENTRYPOINT ["php", "/deps/vendor/bin/php-cs-fixer", "fix", "--config=/app/.php-cs-fixer.php"]

WORKDIR /app
