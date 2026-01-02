FROM php:8.1-fpm

# Install extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install GD for image processing
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

WORKDIR /var/www/html/backoffice
