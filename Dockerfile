ARG WP_VERSION=latest
FROM wordpress:$WP_VERSION

RUN docker-php-ext-install pdo_mysql
RUN apt-get update && apt-get install -y --no-install-recommends jq lftp unzip mariadb-client ssh

# Install xdebug
RUN yes | pecl install xdebug \
    && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/xdebug.ini \
    && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/xdebug.ini

# cleanup
RUN apt-get clean
RUN rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

COPY initwp.sh /usr/local/bin/initwp.sh

ENTRYPOINT [ "initwp.sh" ]
