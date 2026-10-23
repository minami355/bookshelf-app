FROM php:8.5.12-cli-bookworm

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=Asia/Tokyo

# 必要なシステムパッケージ
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    supervisor \
    gosu \
    bash \
    ca-certificates \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libicu-dev \
    libzip-dev \
    libmagickwand-dev \
    && rm -rf /var/lib/apt/lists/*

# PHP拡張機能
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    bcmath \
    pcntl \
    exif \
    gd \
    intl \
    zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Laravel Sailで使用するユーザー
RUN groupadd -g 1000 sail \
    && useradd -m -s /bin/bash -u 1337 -g sail sail

# Sailの起動設定
COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /usr/local/etc/php/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container

ENV SUPERVISOR_PHP_USER=sail
ENV SUPERVISOR_PHP_COMMAND="/usr/local/bin/php artisan serve --host=0.0.0.0 --port=80"

EXPOSE 80

ENTRYPOINT ["start-container"]