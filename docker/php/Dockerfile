FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create non-root user
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN groupadd -g ${GROUP_ID} www && \
    useradd -u ${USER_ID} -g www -s /bin/bash -m www && \
    mkdir -p /home/www/.composer && \
    chown -R www:www /home/www

# Set working directory
WORKDIR /var/www/html

# Configure PHP-FPM
RUN echo "pm = dynamic" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_children = 10" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.start_servers = 3" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.min_spare_servers = 2" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_spare_servers = 5" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_requests = 500" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "request_terminate_timeout = 30" >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy custom php.ini
COPY php.ini /usr/local/etc/php/conf.d/99-custom.ini

# Switch to non-root user
USER www

EXPOSE 9000

CMD ["php-fpm"]