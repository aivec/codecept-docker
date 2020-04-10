#!/bin/bash

# mute CMD from official wordpress image
sed -i -e 's/^exec "$@"/#exec "$@"/g' /usr/local/bin/docker-entrypoint.sh

# setting the XDEBUG_CONFIG environment variable doesn't seem to work so we hardcode it here
echo xdebug.remote_host=${DOCKER_BRIDGE_IP} >> /usr/local/etc/php/conf.d/xdebug.ini
# set xdebug port number
echo xdebug.remote_port=${XDEBUG_PORT} >> /usr/local/etc/php/conf.d/xdebug.ini

# execute bash script from official wordpress image
docker-entrypoint.sh apache2-foreground

# execute CMD
exec "$@"
exec apache2-foreground
