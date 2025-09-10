# -----------------------------------------------------------------------------
# Stage 0: Composer deps to provide vendor/ for the asset pipeline (no autoloader)
# -----------------------------------------------------------------------------
FROM composer:2 AS composer_for_assets
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader \
    --ignore-platform-req=ext-exif \
    --ignore-platform-req=ext-gd

# -----------------------------------------------------------------------------
# Stage 1: Frontend assets (Node 16 + Gulp at repo root)
# -----------------------------------------------------------------------------
FROM node:16-bullseye AS assets
WORKDIR /app

# Tooling for older gulp/node-sass stacks
RUN apt-get update && apt-get install -y --no-install-recommends python3 make g++ \
 && rm -rf /var/lib/apt/lists/*

# Node deps + gulp-cli
COPY package*.json ./
RUN npm ci || npm install
RUN npm i -g gulp-cli

# Provide vendor/ so gulp globs like vendor/moxiecode/... resolve
COPY --from=composer_for_assets /app/vendor ./vendor

# Copy sources the gulpfile reads
COPY gulpfile.js ./
COPY . .

# Build ONLY (avoid prod/watch to dodge cleanCSS error)
RUN gulp build

# Expose CKEditor at the path requested by the UI (/node_modules/...)
RUN set -eux; \
  if [ -f node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js ]; then \
    mkdir -p /ckeditor-export/node_modules/@ckeditor/ckeditor5-build-classic/build; \
    cp node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js \
       /ckeditor-export/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js; \
  fi

# -----------------------------------------------------------------------------
# Stage 2: Runtime (PHP 8.2 + Apache)
# -----------------------------------------------------------------------------
FROM php:8.2-apache

# System libs + PHP extensions (ADD: mysqli)
RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev unzip git pkg-config libonig-dev \
      libpng-dev libjpeg-dev libfreetype6-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli zip gd exif mbstring \
  && a2enmod rewrite headers expires \
  && rm -rf /var/lib/apt/lists/*

# Suppress FQDN warning
RUN printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
  && a2enconf servername

WORKDIR /var/www/html

# App code
COPY . .

# Composer (full install WITH autoloader now that code is present)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

# Bring built assets from the assets stage (include fonts to avoid 404s)
COPY --from=assets /app/assets/css    ./assets/css
COPY --from=assets /app/assets/js     ./assets/js
COPY --from=assets /app/assets/lib    ./assets/lib
COPY --from=assets /app/assets/img    ./assets/img
COPY --from=assets /app/assets/fonts  ./assets/fonts

# CKEditor at requested URL path
COPY --from=assets /ckeditor-export/node_modules /var/www/html/node_modules

# Ensure writable upload/temp/session directories
RUN mkdir -p /var/www/html/upload/files /var/www/html/upload/temp /var/www/html/temp/php-sessions \
 && chown -R www-data:www-data /var/www/html/upload /var/www/html/temp

# PHP runtime defaults (writable session path; safe limits)
RUN printf "session.save_path=/var/www/html/temp/php-sessions\nmemory_limit=256M\n" > /usr/local/etc/php/conf.d/zz-projectsend.ini

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
