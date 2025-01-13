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

# PHP.ini ayarlarını doğrudan ekle
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_execution_time = 180" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "max_input_time = 180" >> /usr/local/etc/php/conf.d/custom.ini

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Uygulama dosyalarını kopyala
COPY . /var/www/html/

# Composer bağımlılıklarını yükle (composer.json varsa)
RUN if [ -f "composer.json" ]; then composer install --no-dev --optimize-autoloader; fi

# Apache için doğru izinleri ayarla
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# .env dosyasını .env.example'dan kopyalamak yerine mevcut .env dosyasını kullan
COPY .env .env

EXPOSE 80

CMD ["apache2-foreground"] 