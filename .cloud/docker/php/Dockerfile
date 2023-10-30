FROM php:8.2-apache as prod

# Set working directory
WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER 1
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
ENV PORT 80

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Install system dependencies
# RUN apt-get update \
#     && apt-get install -y \
#         git \
#         curl \
#         libpng-dev \
#         libonig-dev \
#         libxml2-dev \
#         zip \
#         unzip \
#     && apt-get clean \
#     && rm -rf /var/lib/apt/lists/*

# Install nodejs
RUN curl -sL https://deb.nodesource.com/setup_18.x | bash

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libz-dev \
        libpq-dev \
        libjpeg-dev \
        libpng-dev \
        libssl-dev \
        libzip-dev \
        unzip \
        zip \
        git \
        nodejs \
    && apt-get clean \
    && pecl install redis \
    && docker-php-ext-configure gd \
    && docker-php-ext-configure zip \
    && docker-php-ext-install \
        gd \
        exif \
        opcache \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        pcntl \
        zip \
        # mbstring \
        bcmath \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*;

RUN pecl install apcu-5.1.18; \
    pecl clear-cache;

# Install PHP extensions
RUN docker-php-ext-enable apcu

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add the composer.json
COPY ./composer.* ./
COPY ./database ./database
RUN composer install --no-scripts --no-dev

# Copy all files
COPY . ./
RUN chmod -R 777 storage
# RUN composer install --classmap-authoritative --no-dev

# Configure image
COPY .cloud/docker/php/image-root /

RUN a2enmod rewrite
