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

RUN mkdir -p /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/bootstrap/cache
RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN npm ci

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

RUN if [ ! -f .env ]; then cp .env.example .env; fi

RUN mkdir -p storage/framework/views
RUN chmod -R 755 storage/framework/views

RUN php artisan key:generate --no-interaction
RUN php artisan config:cache
RUN php artisan view:clear
RUN php artisan cache:clear

RUN php artisan wayfinder:generate --with-form

RUN npm run build
RUN npm prune --omit=dev

RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

EXPOSE 9000

CMD ["php-fpm"]
