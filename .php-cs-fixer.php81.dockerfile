FROM php:8.1-cli

RUN apt-get update \
  && apt-get install --yes --no-install-recommends \
    git \
    unzip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
