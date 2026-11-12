#!/bin/sh
# Container entrypoint.
#   web  (default) : fix volume permissions, start PHP-FPM + nginx.
#   anything else  : run that command (e.g. "drush cron" for the cron service).
set -e

files_dir=/var/www/html/web/sites/default/files
private_dir=/var/www/html/web/sites/default/private
oauth_key_dir="$private_dir/simple_oauth"

# Railway volumes are mounted as root; Drupal (www-data) must be able to write.
mkdir -p "$files_dir"
chown -R www-data:www-data "$files_dir" 2>/dev/null || true

# Drupal must be able to create private:// protection files as www-data.
mkdir -p "$private_dir"
chown www-data:www-data "$private_dir" 2>/dev/null || true
chmod 700 "$private_dir" 2>/dev/null || true

# Materialize OAuth signing keys from base64 environment variables into the
# image's private site directory. The private key never enters config or Git.
if [ -n "${SIMPLE_OAUTH_PRIVATE_KEY_B64:-}" ] && [ -n "${SIMPLE_OAUTH_PUBLIC_KEY_B64:-}" ]; then
  mkdir -p "$oauth_key_dir"
  umask 077
  printf '%s' "$SIMPLE_OAUTH_PRIVATE_KEY_B64" | base64 -d > "$oauth_key_dir/private.key"
  printf '%s' "$SIMPLE_OAUTH_PUBLIC_KEY_B64" | base64 -d > "$oauth_key_dir/public.key"
  chown -R www-data:www-data "$oauth_key_dir"
fi

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
