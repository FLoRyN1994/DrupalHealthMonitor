#!/bin/sh
# Railway pre-deploy command. Runs before each new version receives traffic.
#   First deploy (empty database): install Drupal from the exported config.
#   Every later deploy: drush deploy (updb, config import, cache rebuild, deploy hooks).
set -e
cd /var/www/html

if drush status --field=bootstrap 2>/dev/null | grep -q Successful; then
  echo "Existing site found: running drush deploy."
  drush deploy -y
else
  echo "No installed site found: installing from config/sync."
  drush site:install --existing-config -y \
    --account-name="${DRUPAL_ADMIN_USER:-admin}" \
    ${DRUPAL_ADMIN_PASS:+--account-pass="$DRUPAL_ADMIN_PASS"}
fi
