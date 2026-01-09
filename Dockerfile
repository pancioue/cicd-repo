# Dockerfile (Cloud Run-friendly)
FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    git unzip libzip-dev \
  && docker-php-ext-install pdo_mysql zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# 先複製 composer 檔案利用 cache
COPY app/composer.json app/composer.lock ./

ARG APP_ENV=local

RUN if [ "$APP_ENV" = "production" ]; then \
      composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress; \
    else \
      composer install --no-interaction --prefer-dist --no-scripts --no-progress; \
    fi

# 再複製完整程式碼
COPY app/. .

# ✅ 清 cache（包含 route cache），避免舊快取卡住新路由
RUN php artisan optimize:clear || true

RUN echo "$APP_ENV"
# ✅ production 才做 optimize（會產生 route/config/view cache）
RUN if [ "$APP_ENV" = "production" ]; then \
      php artisan optimize; \
    else \
      echo "Skip optimize (APP_ENV=$APP_ENV)"; \
    fi

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Cloud Run 會注入 PORT 環境變數（通常 8080）
ENV PORT=8080

# 重要：一定要綁 0.0.0.0，而且 port 用 $PORT
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public public/index.php"]
