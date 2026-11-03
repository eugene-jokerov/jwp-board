# Образ PHP-FPM для headless API (Laravel 13).
# Сборка: `make build` (Makefile передаёт APP_UID/APP_GID текущего пользователя хоста).
FROM php:8.4-fpm

ARG TIMEZONE=Europe/Moscow
ARG APP_USER=jwp
ARG APP_UID=1000
ARG APP_GID=1000

# Пользователь контейнера совпадает по uid/gid с хостом,
# иначе файлы (storage/, bootstrap/cache) будут принадлежать root.
RUN groupadd --gid "$APP_GID" "$APP_USER" \
    && useradd --system --create-home --home-dir "/home/$APP_USER" \
        --uid "$APP_UID" --gid "$APP_GID" --shell /bin/bash "$APP_USER"

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        zip \
        curl \
        procps \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libonig-dev \
        default-mysql-client \
        rsync \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install -j"$(nproc)" \
        pdo \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
        mbstring \
        exif \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer 2 (стабильная версия из официального образа).
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Xdebug ставим всегда, но по умолчанию выключен (PHP_XDEBUG_MODE=off),
# чтобы не замедлять phpstan/phpunit. Включается переменной окружения в .env.
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Часовой пояс и настройки PHP.
RUN ln -snf "/usr/share/zoneinfo/$TIMEZONE" /etc/localtime \
    && echo "$TIMEZONE" > /etc/timezone

COPY php.ini /usr/local/etc/php/conf.d/zz-jwp.ini
COPY xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

USER $APP_USER

EXPOSE 9000

CMD ["php-fpm"]
