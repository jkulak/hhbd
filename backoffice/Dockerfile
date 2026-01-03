FROM php:7.4-fpm

# Install PHP extensions
RUN docker-php-ext-install mysqli opcache

WORKDIR /var/www/html
COPY . ./
