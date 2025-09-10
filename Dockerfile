# -----------------------------------------------------------------------------
# Stage 1: Frontend assets (Node 16 + Gulp at repo root)
# -----------------------------------------------------------------------------
FROM node:16-bullseye AS assets
WORKDIR /app

# Build tools sometimes needed by older node-sass / gulp stacks
RUN apt-get update && apt-get install -y --no-install-recommends \
      python3 make g++ \
  && rm -rf /var/lib/apt/lists/*

# Node deps and gulp-cli
COPY package*.json ./
RUN npm ci || npm install
RUN npm i -g gulp-cli

# Copy sources (gulp often reads templates/partials)
COPY gulpfile.js ./
COPY . .

# Build optimized assets -> emits into /app/assets/{css,js,lib,img,...}
# Try common task names; stop on first that succeeds
RUN set -eux; \
  (gulp --version && (gulp prod || gulp build || gulp default || gulp)) \
  || (npx gulp prod || npx gulp build || npx gulp)

# Expose CKEditor file at the URL your pages request (/node_modules/...)
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

# Composer (run after code is present so classmap sees includes/)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

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
