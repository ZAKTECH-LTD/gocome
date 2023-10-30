# Create base image
FROM php:8.2-apache as base

RUN apt-get update \
    && apt-get install -y libpq-dev libjpeg-dev libpng-dev libssl-dev libzip-dev unzip zip nodejs exif libicu-dev default-mysql-client \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql intl pcntl opcache gd exif opcache pdo_mysql pdo_pgsql pgsql pcntl zip \
    && apt-get clean \
    && pecl install redis \
    && docker-php-ext-configure gd \
    && docker-php-ext-configure zip \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*;

# Install composer dependencies
FROM composer as deps
ARG APP_HOME=/var/www/html
RUN rm -rf $APP_HOME && mkdir $APP_HOME
WORKDIR $APP_HOME
COPY . $APP_HOME

# Install nodejs
# RUN curl -sL https://deb.nodesource.com/setup_18.x | bash

RUN composer install --prefer-dist --no-scripts --no-progress --no-interaction --optimize-autoloader --no-dev $ARGS --ignore-platform-req=ext-gd --ignore-platform-req=ext-exif
# RUN composer dump-autoload 

# Application Image
FROM base
ARG APP_HOME=/var/www/html
ENV APP_HOME=$APP_HOME
ENV APP_ENV=prod
RUN rm -rf $APP_HOME && mkdir $APP_HOME
WORKDIR $APP_HOME

COPY ./docker/apache/apache.conf /etc/apache2/sites-available/000-default.conf
ADD ./docker/apache/start.sh /

# Turn on mod_rewrite otherwise index.php will appear in the URL
RUN a2enmod rewrite
# change uid and gid of apache to docker user uid/gid
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
RUN chown -R www-data:www-data $APP_HOME
USER www-data

# Copy source files
COPY --from=deps --chown=www-data:www-data $APP_HOME $APP_HOME


EXPOSE 80

# Change to root otherwise apache won't start
USER root
RUN chmod +x /start.sh

CMD ["/start.sh"]
