FROM wordpress:latest

RUN docker-php-ext-install pdo_mysql

# Install composer
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- \
        --filename=composer \
        --install-dir=/usr/bin
RUN apt-get update && apt-get install unzip
