FROM php:8.0-apache

# Sistem bağımlılıklarını ve PHP eklentilerini yükle
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install zip pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Composer'ı yükle
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apache modüllerini etkinleştir
RUN a2enmod rewrite

# PHP yapılandırmasını özelleştir
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Uygulama dosyalarını kopyala
COPY . /var/www/html/

# Composer bağımlılıklarını yükle (composer.json varsa)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Apache için doğru izinleri ayarla
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# .env dosyasını yapılandır (eğer varsa)
COPY .env.example .env

EXPOSE 80

CMD ["apache2-foreground"] 