#!/bin/bash

# mute CMD from official wordpress image
sed -i -e 's/^exec "$@"/#exec "$@"/g' /usr/local/bin/docker-entrypoint.sh

# setting the XDEBUG_CONFIG environment variable doesn't seem to work so we hardcode it here
echo xdebug.remote_host=${DOCKER_BRIDGE_IP} >> /usr/local/etc/php/conf.d/xdebug.ini
# set xdebug port number
echo xdebug.remote_port=${XDEBUG_PORT} >> /usr/local/etc/php/conf.d/xdebug.ini

# PHP doesnt seem to pick up on environment variables when started via apache so we
# have to explicitly list them for apache
if [[ ! -z ${APACHE_ENV_VARS} ]]; then
    echo $APACHE_ENV_VARS | jq -r 'keys[]' | while read key; do
        val=$(echo $APACHE_ENV_VARS | jq -r ".[\"$key\"]")
        echo "export ${key}=${val}" | sudo tee -a /etc/apache2/envvars >/dev/null
    done
fi

# execute bash script from official wordpress image
docker-entrypoint.sh apache2-foreground

# download plugins/themes from SSH config(s)
if [[ ! -z ${SSH_CONFIGS} ]]; then
    sshdir=/var/www/html/ssh
    mkdir -p $sshdir/plugins
    mkdir -p $sshdir/themes
    h2 "Pulling non-free plugins/themes from SSH server via scp. This may take some time..."
    configcount=$(echo $SSH_CONFIGS | jq -r '. | length')
    configcount=$(($configcount - 1))
    configi=0
    while [ $configi -le $configcount ]; do
        config=$(echo $SSH_CONFIGS | jq -r --arg index "$configi" '.[$index | tonumber]')
        host=$(echo $config | jq -r '.["host"]')
        user=$(echo $config | jq -r '.["user"]')
        plugins=$(echo $config | jq -r '.["plugins"]')
        themes=$(echo $config | jq -r '.["themes"]')
        privateKeyFilename=$(echo $config | jq -r '.["privateKeyFilename"]')
        privateKeyPath="$sshdir/$privateKeyFilename"
        chmod 600 $privateKeyPath

        if [ "$plugins" != "null" ] && [ ! -z "$plugins" ]; then
            plugincount=$(echo $plugins | jq -r '. | length')
            plugincount=$(($plugincount - 1))
            plugini=0
            while [ $plugini -le $plugincount ]; do
                path=$(echo $plugins | jq -r --arg index "$plugini" '.[$index | tonumber]')
                file="$path.zip"
                scp -o StrictHostKeyChecking=no -i $privateKeyPath $user@$host:${file} $sshdir/ssh/plugins
                plugini=$(($plugini + 1))
            done
        fi

        if [ "$themes" != "null" ] && [ ! -z "$themes" ]; then
            themecount=$(echo $themes | jq -r '. | length')
            themecount=$(($themecount - 1))
            themei=0
            while [ $themei -le $themecount ]; do
                path=$(echo $themes | jq -r --arg index "$themei" '.[$index | tonumber]')
                file="$path.zip"
                scp -o StrictHostKeyChecking=no -i $privateKeyPath $user@$host:${file} $sshdir/ssh/themes
                themei=$(($themei + 1))
            done
        fi

        configi=$(($configi + 1))
    done
fi

# execute CMD
exec "$@"
exec apache2-foreground
