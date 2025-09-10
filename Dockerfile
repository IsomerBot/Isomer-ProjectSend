# -----------------------------------------------------------------------------
# Stage 1: Frontend assets (Node + Gulp at repo root)
# -----------------------------------------------------------------------------
FROM node:20 AS assets
WORKDIR /app

# Node deps
COPY package*.json ./
RUN npm ci

# Build tooling & sources
COPY gulpfile.js ./
COPY assets ./assets
COPY . .        # if gulp reads templates/partials/etc.

# Build optimized assets -> emits into /app/assets/{css,js,lib,img,...}
RUN npx gulp prod || npx gulp build

# Expose CKEditor file at the path the app requests (/node_modules/...)
RUN set -eux; \
  if [ -f node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js ]; then \
    mkdir -p /ckeditor-export/node_modules/@ckeditor/ckeditor5-build-classic/build; \
    cp node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js \
       /ckeditor-export/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js; \
  fi


# -----------------------------------------------------------------------------
# Stage 2: Composer deps (vendor/)
# -----------------------------------------------------------------------------
FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock ./
# Ignore platform reqs here; extensions will exist in the runtime stage
RUN composer install \
    --no-dev --prefer-dist --no-interaction --no-scripts \
    --ignore-platform-req=ext-exif \
    --ignore-platform-req=ext-gd


# -----------------------------------------------------------------------------
# Stage 3: Runtime (PHP 8.2 + Apache)
# -----------------------------------------------------------------------------
FROM php:8.2-apache

# System libs + PHP extensions (include mbstring to avoid blank page)
RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev unzip git libpng-dev libjpeg-dev libfreetype6-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install pdo pdo_mysql zip gd exif mbstring \
  && a2enmod rewrite headers expires \
  && rm -rf /var/lib/apt/lists/*

# Suppress FQDN warning
RUN printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
  && a2enconf servername

WORKDIR /var/www/html

# App code
COPY . .

# Vendor from composer stage
COPY --from=composer_deps /app/vendor ./vendor

# Built assets from Node stage
COPY --from=assets /app/assets/css ./assets/css
COPY --from=assets /app/assets/js  ./assets/js
COPY --from=assets /app/assets/lib ./assets/lib
COPY --from=assets /app/assets/img ./assets/img

# CKEditor file at requested URL path
COPY --from=assets /ckeditor-export/node_modules /var/www/html/node_modules

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
