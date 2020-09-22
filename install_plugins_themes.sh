#!/bin/bash

dirs=(/var/www/html/ssh /var/www/html/ftp)
for dir in "${dirs[@]}"; do
    plugins=($(find . -maxdepth 1 -name '*.zip'))
    echo "Installing downloaded plugins..." |& logger
    for zipfile in "${plugins[@]}"; do
        echo "$zipfile" |& logger
        wp plugin install $dir/plugins/$zipfile |& logger
        rm "$zipfile"
    done

    cd $dir/themes
    themes=($(find . -maxdepth 1 -name '*.zip'))
    echo "Installing downloaded themes..." |& logger
    for zipfile in "${themes[@]}"; do
        echo "$zipfile" |& logger
        wp theme install $dir/themes/$zipfile |& logger
        rm "$zipfile"
    done
done
