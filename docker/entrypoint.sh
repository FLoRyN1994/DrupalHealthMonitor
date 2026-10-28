#!/bin/sh
# Container entrypoint.
#   web  (default) : fix volume permissions, start PHP-FPM + nginx.
#   anything else  : run that command (e.g. "drush cron" for the cron service).
set -e

files_dir=/var/www/html/web/sites/default/files

# Railway volumes are mounted as root; Drupal (www-data) must be able to write.
mkdir -p "$files_dir"
chown -R www-data:www-data "$files_dir" 2>/dev/null || true

if [ "$1" = "web" ]; then
  # Debian's nginx package re-creates this symlink via a dpkg trigger during
  # the image build (after install-php-extensions runs its own apt install/purge
  # cycle); remove it here too so port 80 never serves the stock "Welcome to
  # nginx" page instead of our vhost on $PORT.
  rm -f /etc/nginx/sites-enabled/default
  # Render nginx config with Railway's $PORT.
  envsubst '${PORT}' < /etc/nginx/templates/drupal.conf.template > /etc/nginx/conf.d/drupal.conf
  php-fpm --daemonize
  exec nginx -g 'daemon off;'
fi

exec "$@"
