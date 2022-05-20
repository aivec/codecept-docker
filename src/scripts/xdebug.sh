#!/bin/bash

# pulled from https://xdebug.org/docs/all_settings:
# "Warning: Some web servers have a configuration option to prevent environment variables from being
# propagated to PHP and Xdebug. For example, PHP-FPM has a clear_env configuration setting that is on
# by default, which you will need to turn off if you want to use XDEBUG_CONFIG. Make sure that your
# web server does not clean the environment, or specifically allows the XDEBUG_CONFIG environment variable
# to be passed on."
#
# This bizarre behavior was finally explained in the docs. This is why we hardcode 'client_host' here
sudo sed -i '/^xdebug.client_host/d' /usr/local/etc/php/conf.d/xdebug.ini
sudo sh -c "echo 'xdebug.client_host=${DOCKER_BRIDGE_IP}' >> /usr/local/etc/php/conf.d/xdebug.ini"
