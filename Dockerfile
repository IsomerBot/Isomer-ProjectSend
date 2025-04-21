# Use PHP 8.1 with Apache
FROM php:8.1-apache

# Set version label
LABEL maintainer="Isomer"

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mysqli \
    exif \
    gd \
    bcmath \
    soap

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configure Apache
RUN a2enmod rewrite

# Create necessary directories
RUN mkdir -p \
    /var/www/html \
    /config \
    /data

# Set working directory
WORKDIR /var/www/html

# Copy your local ProjectSend files
COPY . /var/www/html/

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node.js dependencies and build assets
RUN npm install -g gulp-cli && \
    npm install && \
    gulp build

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Set proper permissions
RUN chown -R www-data:www-data \
    /var/www/html \
    /config \
    /data

# Expose ports
EXPOSE 80

# Define volumes
VOLUME /config /data 