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

# Copy custom PHP configuration to increase upload limits
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Copy all application files to Apache's document root
COPY . /var/www/html/

# Create directories for uploads and credentials within the document root.
# Grant the web server user (www-data) write permissions to these directories.
# This is crucial so that the application can save uploaded files and access tokens.
RUN mkdir -p /var/www/html/uploads /var/www/html/credentials && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/uploads /var/www/html/credentials

# Enable Apache's rewrite module for potential .htaccess usage
RUN a2enmod rewrite

# Set proper working directory
WORKDIR /var/www/html

# Add required directory permissions to Apache config to allow access to the document root.
RUN echo '\n<Directory /var/www/html>\n    Options Indexes FollowSymLinks\n    AllowOverride All\n    Require all granted\n</Directory>\n' >> /etc/apache2/apache2.conf

# Expose port 80 for web traffic
EXPOSE 80

# Start Apache in the foreground
CMD ["apache2-foreground"] 