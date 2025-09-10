# -----------------------------------------------------------------------------
# Stage 1: Frontend assets (Node + Gulp at repo root)
# -----------------------------------------------------------------------------
FROM node:20 AS assets
WORKDIR /app

# Install node deps (root-level package.json has gulp & deps)
COPY package*.json ./
RUN npm ci

# Copy build tooling and sources, then run gulp
COPY gulpfile.js ./
COPY assets ./assets
# If your gulp tasks read other files (templates), copy the rest too:
COPY . .

# Build optimized assets -> emits into /app/assets/{css,js,lib,...}
RUN npx gulp prod || npx gulp build

# Also expose CKEditor where the app expects it (prevents 404 to /node_modules/...)
# Only copies that single file path to keep image slim.
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
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts


# -----------------------------------------------------------------------------
# Stage 3: Runtime (PHP 8.2 + Apache)
# -----------------------------------------------------------------------------
FROM php:8.2-apache

# System libs + PHP extensions (adds mbstring to avoid white screen)
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

# Copy application code
COPY . .

# Composer vendor from build stage
COPY --from=composer_deps /app/vendor ./vendor

# Copy built assets from Node stage
COPY --from=assets /app/assets/css ./assets/css
COPY --from=assets /app/assets/js  ./assets/js
COPY --from=assets /app/assets/lib ./assets/lib
# (Optional) If your gulp adds fonts/images into assets/, bring them too:
COPY --from=assets /app/assets/img ./assets/img

# Expose CKEditor file at the path templates request
COPY --from=assets /ckeditor-export/node_modules /var/www/html/node_modules

# Permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
