FROM php:8.2-apache

RUN a2enmod rewrite

RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
  && docker-php-ext-install pdo_sqlite \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY . /var/www/html

# Ensure writable DB folder
RUN mkdir -p /var/www/html/data \
  && chown -R www-data:www-data /var/www/html \
  && chmod -R 775 /var/www/html

EXPOSE 80
