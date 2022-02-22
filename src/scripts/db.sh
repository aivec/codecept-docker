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

# acceptance/functional/selenium tests reference DB_NAME
# as-is in the container's wp-config.php so we need to make
# sure it points to our acceptance database.
#
# integration (wpunit suite) tests, on the other hand, use the
# TEST_DB_NAME environment variable defined in .env.testing instead
# of reading the DB_NAME constant from wp-config.php, so we don't
# need DB_NAME set for integration tests.
wp config set DB_NAME ${ACCEPTANCE_DB_NAME}
