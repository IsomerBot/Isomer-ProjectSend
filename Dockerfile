# ---------- Build frontend assets (only if repo has assets/package.json) ----------
FROM node:20 AS assets
WORKDIR /app

# Copy package files first for better caching (adjust path if needed)
COPY assets/package*.json ./assets/
# Install deps only if package.json exists
RUN [ -f ./assets/package.json ] && cd assets && npm ci || true

# Copy sources and run build (no-op if no package.json)
COPY assets ./assets
RUN [ -f ./assets/package.json ] && cd assets && npm run build || true


# ---------- PHP + Apache runtime ----------
FROM php:8.2-apache

# System deps + PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev unzip git libpng-dev libjpeg-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo pdo_mysql zip gd exif \
 && a2enmod rewrite headers expires \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

# Copy app code
COPY . .

# Bring in built frontend outputs from the assets stage if present
# (We copy the whole assets dir to /tmp then move specific folders if they exist)
COPY --from=assets /app/assets /tmp/assets
RUN set -eux; \
    if [ -d /tmp/assets/css ]; then cp -r /tmp/assets/css /var/www/html/assets/; fi; \
    if [ -d /tmp/assets/dist ]; then cp -r /tmp/assets/dist /var/www/html/assets/; fi

# Composer (same as your original)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
