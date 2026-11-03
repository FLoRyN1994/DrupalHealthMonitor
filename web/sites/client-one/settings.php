<?php

declare(strict_types=1);

if (getenv('IS_DDEV_PROJECT') !== 'true') {
  throw new RuntimeException('The client-one demo site is available only in DDEV.');
}

$databases['default']['default'] = [
  'database' => 'hm_client_one',
  'username' => 'db',
  'password' => 'db',
  'host' => 'db',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$settings['trusted_host_patterns'] = ['^client-one\.ddev\.site$'];
$settings['file_public_path'] = 'sites/client-one/files';
$settings['file_private_path'] = __DIR__ . '/private';
$settings['config_sync_directory'] = __DIR__ . '/files/config-sync';
$settings['skip_permissions_hardening'] = TRUE;

if (file_exists(__DIR__ . '/settings.local.php')) {
  require __DIR__ . '/settings.local.php';
}
$databases['default']['default'] = array (
  'database' => 'hm_client_one',
  'username' => 'db',
  'password' => 'db',
  'prefix' => '',
  'host' => 'db',
  'port' => '3306',
  'isolation_level' => 'READ COMMITTED',
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
);
$settings['hash_salt'] = 'PXZLl6iF2fWujND9BlWZ8fdMFcV-8FImj1DmFXJMczq6DCn5HkrM0RujLRLYy8FXwfTEoYioJg';
