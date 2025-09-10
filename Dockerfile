# -----------------------------------------------------------------------------
# Stage 0: Composer deps just to provide vendor/ to the Gulp build
# -----------------------------------------------------------------------------
FROM composer:2 AS composer_for_assets
WORKDIR /app
COPY composer.json composer.lock ./
# We only need vendor files for the asset pipeline; ignore ext reqs here.
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts \
    --ignore-platform-req=ext-exif \
    --ignore-platform-req=ext-gd

# -----------------------------------------------------------------------------
# Stage 1: Frontend assets (Node 16 + Gulp at repo root)
# -----------------------------------------------------------------------------
FROM node:16-bullseye AS assets
WORKDIR /app

# Tooling that older gulp/node-sass stacks sometimes need
RUN apt-get update && apt-get install -y --no-install-recommends \
      python3 make g++ \
  && rm -rf /var/lib/apt/lists/*

# Node deps + gulp-cli
COPY package*.json ./
RUN npm ci || npm install
RUN npm i -g gulp-cli

# Bring vendor/ from composer stage so Gulp globs resolve (plupload css path)
COPY --from=composer_for_assets /app/vendor ./vendor

# Copy sources the gulpfile reads
COPY gulpfile.js ./
COPY . .

# Build ONLY (avoid 'prod' and 'watch' which cause your errors)
RUN gulp build

# Also expose CKEditor file under the URL your pages request
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

# System libs + PHP extensions (mbstring requires libonig-dev)
RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev unzip git pkg-config libonig-dev \
      libpng-dev libjpeg-dev libfreetype6-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql zip gd exif mbstring \
  && a2enmod rewrite headers expires \
  && rm -rf /var/lib/apt/lists/*

# Suppress FQDN warning
RUN printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
  && a2enconf servername

WORKDIR /var/www/html

# App code
COPY . .

# Install Composer deps in the runtime image (proper extensions present)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

# Copy the built assets from the assets stage
COPY --from=assets /app/assets/css ./assets/css
COPY --from=assets /app/assets/js  ./assets/js
COPY --from=assets /app/assets/lib ./assets/lib
COPY --from=assets /app/assets/img ./assets/img

# Provide CKEditor at the requested /node_modules/... path
COPY --from=assets /ckeditor-export/node_modules /var/www/html/node_modules

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
