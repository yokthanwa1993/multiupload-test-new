# Use PHP CLI image for simpler deployment
FROM php:8.1-cli

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
    curl \
    && rm -rf /var/lib/apt/lists/*

# Configure and install required PHP extensions for the project.
# mbstring: For multi-byte strings (e.g., YouTube titles).
# fileinfo: For mime_content_type to detect file types.
# gd: For image manipulation (thumbnails).
# curl: For making API requests.
# zip: For potential future archive handling.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install mbstring fileinfo gd curl pdo pdo_mysql zip

# Set the working directory
WORKDIR /var/www/html

# Copy custom PHP configuration to increase upload limits
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Copy all application files
COPY . .

# Create directories and set permissions
RUN mkdir -p uploads credentials && \
    chmod -R 755 uploads credentials

# Expose port 80 for web traffic
EXPOSE 80

# Add health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:80/health.php || exit 1

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:80", "-t", "/var/www/html"] 