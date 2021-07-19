#!/bin/bash

source ${AVC_SCRIPTS_DIR}/logging.sh

if [ $RUNNING_FROM_CACHE -eq 1 ]; then
    h2 'Waiting for MySQL to initialize...'
    while ! mysqladmin ping \
        --host="${DB_HOST:-db}" \
        --user="${DB_USER:-root}" \
        --password="${DB_PASS:-root}" \
        --silent >/dev/null; do
        sleep 1
    done
fi

h2 "Setting Up Integration Database..."
wp config set DB_NAME ${INTEGRATION_DB_NAME}
wp db drop --yes &> /dev/null
wp db create
wp core install

if [ $RUNNING_FROM_CACHE -eq 1 ]; then
    h2 "Setting Up Acceptance Database..."
    wp config set DB_NAME ${ACCEPTANCE_DB_NAME}
    wp db drop --yes &> /dev/null
    wp db create
    wp core install
fi
