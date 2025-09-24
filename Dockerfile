FROM php:8.3-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install mbstring exif pcntl bcmath gd
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . .

RUN chmod -R 775 /var/www/html/bootstrap/cache
RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN npm ci --only=production

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

RUN npm run build

RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

RUN if [ ! -f .env ]; then cp .env.example .env; fi

EXPOSE 9000

CMD ["php-fpm"]
