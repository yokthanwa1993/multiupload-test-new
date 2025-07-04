# Use an official PHP image with Apache, which is well-suited for this project.
# Using PHP 8.1 as a stable base.
# Force rebuild: 2025-07-04-04:26:00
FROM php:8.1-apache

# Install system dependencies required for PHP extensions
RUN apt-get update && apt-get install -y \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libcurl4-openssl-dev \
    zip \
    unzip \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure and install required PHP extensions for the project.
# mbstring: For multi-byte strings (e.g., YouTube titles).
# fileinfo: For mime_content_type to detect file types.
# gd: For image manipulation (thumbnails).
# curl: For making API requests.
# zip: For potential future archive handling.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mbstring fileinfo gd curl pdo pdo_mysql zip

# Set the working directory to /app, standard for Coolify
WORKDIR /app

# Copy custom PHP configuration to increase upload limits
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Copy all application files from the current directory to the /app directory in the container.
COPY . .

# Create directories for uploads and credentials within the workdir.
# Grant the web server user (www-data) write permissions to these directories.
# This is crucial so that the application can save uploaded files and access tokens.
RUN mkdir -p uploads credentials && \
    chown -R www-data:www-data uploads credentials && \
    chmod -R 775 uploads credentials

# Add a command to list directory permissions for debugging during deployment.
# This will show up in the Coolify build logs.
RUN ls -la /app

# Apache in the base image is already configured to point to /var/www/html.
# We need to change Apache's document root to our new working directory /app.
RUN sed -i 's!/var/www/html!/app!g' /etc/apache2/sites-available/000-default.conf

# Enable Apache's rewrite module for potential .htaccess usage
RUN a2enmod rewrite

# Add required directory permissions to Apache config to allow access to the /app directory.
RUN echo '\n<Directory /app>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

# The apache server in the base image is already configured to expose port 80. 