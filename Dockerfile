# -----------------------------------------------------------------------------
# Stage 1: Frontend assets (build CSS/JS and provide CKEditor file expected by UI)
# -----------------------------------------------------------------------------
FROM node:20 AS assets
WORKDIR /app

# Copy only what we need first for better caching
# (Adjust paths if your package.json is not under assets/)
COPY assets/package*.json ./assets/

# Install deps if package.json exists (no-op otherwise)
RUN [ -f ./assets/package.json ] && cd assets && npm ci || true

# Copy sources and run build (no-op if no package.json)
COPY assets ./assets
RUN [ -f ./assets/package.json ] && cd assets && npm run build || true

# CKEditor: some templates request it from /node_modules/...
# We’ll expose ONLY the ckeditor build at the expected path to avoid 404s.
# (If your build bundles CKEditor elsewhere, this is harmless.)
RUN set -eux; \
  if [ -f assets/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js ]; then \
    mkdir -p /ckeditor-export/node_modules/@ckeditor/ckeditor5-build-classic/build; \
    cp assets/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js \
       /ckeditor-export/node_modules/@ckeditor/ckeditor5-build-classic/build/ckeditor.js; \
  fi


# -----------------------------------------------------------------------------
# Stage 2: PHP dependencies (composer install to vendor/)
# -----------------------------------------------------------------------------
FROM composer:2 AS composer_deps
WORKDIR /app
COPY composer.json composer.lock ./
# If your app uses private repos, add auth here as needed
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts


# -----------------------------------------------------------------------------
# Stage 3: Runtime (PHP 8.2 + Apache)
# -----------------------------------------------------------------------------
FROM php:8.2-apache

# System libs + PHP extensions
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

# Bring in vendor from composer stage (faster, reproducible)
COPY --from=composer_deps /app/vendor ./vendor

# Bring in built assets (copy only if present)
# Adjust these lines if your build outputs to different dirs
COPY --from=assets /app/assets/css ./assets/css
COPY --from=assets /app/assets/js  ./assets/js

# Expose CKEditor file at the path your pages request (prevents 404)
COPY --from=assets /ckeditor-export/node_modules /var/www/html/node_modules

# Permissions for www-data
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
