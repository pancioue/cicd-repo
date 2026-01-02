# Dockerfile
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
  && docker-php-ext-install pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 先複製 composer 檔案利用 cache
COPY app/composer.json app/composer.lock ./

# 用 ARG 讓 CI/Production 可切換 dev 套件
ARG APP_ENV=local

RUN if [ "$APP_ENV" = "production" ]; then \
      composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress; \
    else \
      composer install --no-interaction --prefer-dist --no-scripts --no-progress; \
    fi

# 再複製完整程式碼
COPY app/. .

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

CMD ["php-fpm", "-F"]
