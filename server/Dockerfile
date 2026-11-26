FROM php:8.4-fpm-alpine

# Install system dependencies & PHP extensions
RUN apk add --no-cache \
    curl \
    fcgi \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libxml2-dev \
    icu-dev \
    zip \
    unzip \
    linux-headers \
    $PHPIZE_DEPS \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql bcmath pcntl gd intl zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

# Enable PHP-FPM ping endpoint for container healthchecks
RUN printf '[www]\nping.path = /ping\nping.response = pong\n' > /usr/local/etc/php-fpm.d/zz-healthcheck.conf

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application and install production dependencies
COPY server/ /var/www/html/
RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 9000

HEALTHCHECK --interval=30s --timeout=5s --retries=3 CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong || exit 1

CMD ["php-fpm"]
