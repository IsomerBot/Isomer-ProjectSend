# -----------------------------------------------------------------------------
# Stage 0: Composer deps only for vendor/ (asset pipeline globs)
# -----------------------------------------------------------------------------
FROM composer:2 AS composer_for_assets
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-autoloader \
    --ignore-platform-req=ext-exif \
    --ignore-platform-req=ext-gd

# -----------------------------------------------------------------------------
# Stage 1: Frontend assets (Node 16 + gulp at repo root)
# -----------------------------------------------------------------------------
FROM node:16-bullseye AS assets
WORKDIR /app

# Tooling for old gulp/node-sass stacks
RUN apt-get update && apt-get install -y --no-install-recommends python3 make g++ \
 && rm -rf /var/lib/apt/lists/*

# deps + gulp-cli
COPY package*.json ./
RUN npm ci || npm install
RUN npm i -g gulp-cli

# vendor/ so gulp globs like vendor/moxiecode/... resolve
COPY --from=composer_for_assets /app/vendor ./vendor

# sources used by gulp
COPY gulpfile.js ./
COPY . .

# Build only (avoid prod/watch tasks that require cleanCSS)
RUN gulp build

# CKEditor at the URL your app requests (/node_modules/.../build/ckeditor.js)
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

# System libs + PHP extensions (incl. Imagick)
RUN apt-get update && apt-get install -y --no-install-recommends \
      libzip-dev unzip git pkg-config libonig-dev \
      libpng-dev libjpeg-dev libfreetype6-dev \
      libmagickwand-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j"$(nproc)" pdo pdo_mysql mysqli zip gd exif mbstring \
  && pecl install imagick \
  && docker-php-ext-enable imagick \
  && a2enmod rewrite headers expires \
  && rm -rf /var/lib/apt/lists/*

# Quiet Apache FQDN warning
RUN printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername

WORKDIR /var/www/html

# App code
COPY . .

# PHP runtime tuning (uploads/images)
COPY docker/php/zz-projectsend.ini /usr/local/etc/php/conf.d/zz-projectsend.ini

# Make DB upgrades tolerant + add missing tables in installer if absent
COPY docker/patches/patch-upgrades.sh /usr/local/bin/patch-upgrades.sh
COPY docker/patches/patch-installer.sh /usr/local/bin/patch-installer.sh
RUN bash /usr/local/bin/patch-upgrades.sh && bash /usr/local/bin/patch-installer.sh

# Composer (full install WITH autoloader)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

# Bring built assets
COPY --from=assets /app/assets/css ./assets/css
COPY --from=assets /app/assets/js  ./assets/js
COPY --from=assets /app/assets/lib ./assets/lib
COPY --from=assets /app/assets/img ./assets/img

# CKEditor at requested URL path
COPY --from=assets /ckeditor-export/node_modules /var/www/html/node_modules

# Ensure writable paths at runtime
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Correct ownership of app tree (Apache runs as www-data)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
