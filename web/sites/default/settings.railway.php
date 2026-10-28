<?php

/**
 * @file
 * Production settings for Railway. Loaded by settings.php when RAILWAY_ENVIRONMENT is set.
 *
 * All values come from Railway service variables; nothing secret is committed.
 * Required variables on the "app" service:
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
 *     (reference the MySQL service, e.g. DB_HOST=${{MySQL.MYSQLHOST}})
 *   DRUPAL_HASH_SALT   long random string
 *   DRUPAL_TRUSTED_HOST  e.g. health-monitor.tofdev.com
 */

declare(strict_types=1);

$databases['default']['default'] = [
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'host' => getenv('DB_HOST') ?: 'localhost',
  'port' => getenv('DB_PORT') ?: '3306',
  'database' => getenv('DB_NAME') ?: 'railway',
  'username' => getenv('DB_USER') ?: 'root',
  'password' => getenv('DB_PASSWORD') ?: '',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: '';

// Only answer for the real domain(s), plus Railway's generated *.up.railway.app domain.
$trusted_host = getenv('DRUPAL_TRUSTED_HOST') ?: 'health-monitor.tofdev.com';
$settings['trusted_host_patterns'] = [
  '^' . preg_quote($trusted_host) . '$',
  '^.+\.up\.railway\.app$',
];

// Railway terminates HTTPS at its proxy; trust its X-Forwarded-* headers so Drupal
// generates https:// URLs. The proxy address changes, so trust the current peer.
if (!empty($_SERVER['REMOTE_ADDR'])) {
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR']];
}

// Production hardening.
$config['system.logging']['error_level'] = 'hide';
$config['system.performance']['css']['preprocess'] = TRUE;
$config['system.performance']['js']['preprocess'] = TRUE;
$settings['skip_permissions_hardening'] = FALSE;
$settings['update_free_access'] = FALSE;
