#!/bin/bash

source ${AVC_SCRIPTS_DIR}/logging.sh

h2 "Setting Up Integration Database..."
curdb=$(wp config get DB_NAME)
wp config set DB_NAME ${INTEGRATION_DB_NAME}
wp db drop --yes
wp db create
wp core install
wp config set DB_NAME $curdb
