#!/bin/bash

declare -i term_width=70

h2() {
    printf '\e[1;33m==>\e[37;1m %s\e[0m\n' "$*"
}

logger() {
    fold --width $((term_width - 9)) -s | sed -n '
    /^\x1b\[[0-9;]*m/{ # match any line beginning with colorized text
        s/Error:/  \0/ # pads line so its length matches others
        p              # any lines containing color
        b              # branch to end
    }
    s/.*/         \0/p # pads all other lines with 9 spaces
    '
}

extrasdir=/var/www/html/extras
mkdir -p $extrasdir

# download plugins/themes from SSH config(s)
if [[ ! -z ${SSH_CONFIGS} ]]; then
    sshdir=$extrasdir/ssh
    rm -rf $sshdir/plugins
    rm -rf $sshdir/themes
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
                scp -o StrictHostKeyChecking=no -i $privateKeyPath $user@$host:${file} $sshdir/plugins
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
                scp -o StrictHostKeyChecking=no -i $privateKeyPath $user@$host:${file} $sshdir/themes
                themei=$(($themei + 1))
            done
        fi

        configi=$(($configi + 1))
    done
fi
