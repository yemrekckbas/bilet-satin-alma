# Resmi PHP-Apache görüntüsünü kullan
FROM php:8.2-apache

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Gerekli paketleri yükle (Composer, Git, Unzip, SQLite ve GD için)
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    && docker-php-ext-install pdo_sqlite zip gd

# Composer'ı yükle
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Proje dosyalarını kopyala
COPY . /var/www/html

# Composer bağımlılıklarını yükle (backend klasöründe composer.json var)
RUN cd backend && composer install --no-dev --optimize-autoloader

# Apache rewrite modülünü etkinleştir
RUN a2enmod rewrite

# Apache yapılandırmasını düzenle (DocumentRoot ve dizin izinleri)
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html' \
    '    <Directory /var/www/html>' \
    '        Options Indexes FollowSymLinks' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf

# Varsayılan index dosyasını ayarla
RUN echo 'DirectoryIndex index.php' >> /etc/apache2/mods-enabled/dir.conf

# SQLite veritabanını başlat (init_db.php)
RUN php backend/init_db.php || true

# Portu aç
EXPOSE 80

# Apache'yi başlat
CMD ["apache2-foreground"]
