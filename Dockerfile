# Production image for Railway: PHP 8.4 FPM + nginx in one container.
# Local development uses DDEV, not this file.

# --- Stage 1: install PHP dependencies (no dev tools) ---------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
COPY recipes/ recipes/
COPY web/ web/
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
      --optimize-autoloader --ignore-platform-reqs

# --- Stage 2: runtime ---------------------------------------------------------------
FROM php:8.4-fpm-bookworm

# nginx, envsubst (from gettext-base) for the port template, mysql client for drush sql:*.
RUN apt-get update \
 && apt-get install -y --no-install-recommends nginx gettext-base default-mysql-client unzip \
 && rm -rf /var/lib/apt/lists/*

# PHP extensions Drupal needs, installed with the standard helper script.
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd pdo_mysql opcache zip intl apcu

# Production PHP settings.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-drupal.ini"
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-env.conf

# nginx site config; ${PORT} is filled in at container start.
RUN rm -f /etc/nginx/sites-enabled/default
COPY docker/nginx.conf.template /etc/nginx/templates/drupal.conf.template

WORKDIR /var/www/html
COPY --from=vendor /app/vendor/ vendor/
COPY --from=vendor /app/web/ web/
COPY --from=vendor /app/recipes/ recipes/
COPY composer.json composer.lock ./
COPY config/ config/
COPY scripts/ scripts/
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh scripts/*.sh \
 && mkdir -p web/sites/default/files \
 && chown -R www-data:www-data web/sites/default/files

ENV PATH="/var/www/html/vendor/bin:${PATH}" \
    PORT=8080
EXPOSE 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["web"]
