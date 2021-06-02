#!/bin/bash

sudo chown admin:admin ${AVC_META_DIR}
sudo chown -R admin:admin ${AVC_SCRIPTS_DIR}

${AVC_SCRIPTS_DIR}/db.sh
${AVC_SCRIPTS_DIR}/xdebug.sh
${AVC_SCRIPTS_DIR}/apache_envvars.sh
${AVC_SCRIPTS_DIR}/download_pts.sh
${AVC_SCRIPTS_DIR}/ssh_pts.sh
${AVC_SCRIPTS_DIR}/ftp_pts.sh
${AVC_SCRIPTS_DIR}/startup_state.sh
${AVC_SCRIPTS_DIR}/mailhog.sh
${AVC_SCRIPTS_DIR}/cache-save-pt-lists.php
