FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    curl \
    ca-certificates \
    && docker-php-ext-install pdo pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

WORKDIR /var/www/html/daily-fits
COPY . .

# Ensure data and log directories exist and are writable by the webserver user
RUN mkdir -p /var/www/html/daily-fits/data \
    && mkdir -p /var/www/html/daily-fits/backend/config \
    && chown -R www-data:www-data /var/www/html/daily-fits \
    && chmod -R 775 /var/www/html/daily-fits/data /var/www/html/daily-fits/data/* || true

EXPOSE 80
