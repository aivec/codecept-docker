#!/bin/bash

su - root

# mute CMD from official wordpress image
sed -i -e 's/^exec "$@"/#exec "$@"/g' /usr/local/bin/docker-entrypoint.sh

# setting the XDEBUG_CONFIG environment variable doesn't seem to work so we hardcode it here
echo xdebug.client_host=${DOCKER_BRIDGE_IP} >> /usr/local/etc/php/conf.d/xdebug.ini
# set xdebug port number
echo xdebug.client_port=${XDEBUG_PORT} >> /usr/local/etc/php/conf.d/xdebug.ini

# PHP doesnt seem to pick up on environment variables when started via apache so we
# have to explicitly list them for apache
if [[ ! -z ${APACHE_ENV_VARS} ]]; then
    echo $APACHE_ENV_VARS | jq -r 'keys[]' | while read key; do
        val=$(echo $APACHE_ENV_VARS | jq -r ".[\"$key\"]")
        echo "export ${key}=${val}" | tee -a /etc/apache2/envvars >/dev/null
    done
fi

# execute bash script from official wordpress image
docker-entrypoint.sh apache2-foreground

# execute CMD
exec "$@"
exec apache2-foreground
