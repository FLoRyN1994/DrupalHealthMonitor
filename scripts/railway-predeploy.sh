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
  # A failed Drupal bootstrap is not proof that the database is empty. Refuse
  # to run site:install over any existing schema because that command drops all
  # tables. This turns missing code/settings or a transient failure into a safe
  # failed deployment instead of data loss.
  table_count="$({
    MYSQL_PWD="${DB_PASSWORD:-}" mysql \
      --protocol=TCP \
      --host="${DB_HOST:?DB_HOST is required}" \
      --port="${DB_PORT:-3306}" \
      --user="${DB_USER:?DB_USER is required}" \
      --database="${DB_NAME:?DB_NAME is required}" \
      --batch --skip-column-names \
      --execute='SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();'
  } 2>/dev/null)" || {
    echo "Drupal bootstrap and database inspection both failed; refusing installation." >&2
    exit 1
  }

  if [ "$table_count" -ne 0 ]; then
    echo "Drupal bootstrap failed but the database contains $table_count tables; refusing destructive installation." >&2
    exit 1
  fi

  echo "Empty database found: installing from config/sync."
  drush site:install --existing-config -y \
    --account-name="${DRUPAL_ADMIN_USER:-admin}" \
    ${DRUPAL_ADMIN_PASS:+--account-pass="$DRUPAL_ADMIN_PASS"}
fi
