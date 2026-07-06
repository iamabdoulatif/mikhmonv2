# Mikhmonv2 Docker Image
# Build: docker build -t iamlatif/mikhmonv2:latest .
# Push:  docker push iamlatif/mikhmonv2:latest

FROM php:7.4-fpm

LABEL maintainer="iamlatif"
LABEL description="Mikhmonv2 - MikroTik Hotspot Manager with RouterOS v7 support"

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install zip \
    && docker-php-ext-install mysqli pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . /var/www/

# Create persistent data directory
RUN mkdir -p /var/www/data && chown -R www-data:www-data /var/www/data

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
