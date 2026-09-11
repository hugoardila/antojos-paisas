FROM php:8.2-apache

RUN apt-get update     && apt-get install -y --no-install-recommends libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libzip-dev     && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp     && docker-php-ext-install -j$(nproc) pdo_mysql gd     && a2enmod rewrite headers proxy proxy_http proxy_wstunnel remoteip     && sed -ri 's!^Listen 80$!Listen 127.0.0.1:18081!' /etc/apache2/ports.conf     && sed -ri 's!<VirtualHost \*:80>!<VirtualHost 127.0.0.1:18081>!' /etc/apache2/sites-available/000-default.conf     && sed -ri 's!www-data!daemon!g' /etc/apache2/envvars     && rm -rf /var/lib/apt/lists/*

COPY apache-antojos.conf /etc/apache2/conf-enabled/apache-antojos.conf

WORKDIR /var/www/html

EXPOSE 18081
