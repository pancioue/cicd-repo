# Dockerfile (Cloud Run-friendly)
FROM php:8.4-cli

RUN apt-get update \
  && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
  && docker-php-ext-install pdo_mysql zip \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY app/composer.json app/composer.lock ./

ARG APP_ENV=local

RUN if [ "$APP_ENV" = "production" ]; then \
      composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress; \
    else \
      composer install --no-interaction --prefer-dist --no-scripts --no-progress; \
    fi

COPY app/. .

RUN php artisan optimize:clear || true \
  && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

ENV PORT=8080
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public public/index.php"]
