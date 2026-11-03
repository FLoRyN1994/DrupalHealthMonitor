<?php

/**
 * @file
 * Registers generated local demo clients in the Health Monitor.
 */

declare(strict_types=1);

use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Site;
use Drupal\key\Entity\Key;

if (getenv('IS_DDEV_PROJECT') !== 'true') {
  throw new RuntimeException('Demo clients can be registered only in DDEV.');
}

if (!Drupal::moduleHandler()->moduleExists('hm_core')) {
  print "Health Monitor Core is not installed; skipping demo registration.\n";
  return;
}

/**
 * Loads a generated agent secret from a demo site's local settings.
 */
$load_secret = static function (string $site_name): string {
  $variables = (static function (string $settings_file): array {
    require $settings_file;
    return get_defined_vars();
  })(DRUPAL_ROOT . '/sites/' . $site_name . '/settings.local.php');
  $settings = $variables['settings'] ?? NULL;
  $secret = is_array($settings) ? ($settings['hm_agent_secret'] ?? NULL) : NULL;
  if (!is_string($secret) || $secret === '') {
    throw new RuntimeException(sprintf('No agent secret exists for %s.', $site_name));
  }
  return $secret;
};

/**
 * Loads or creates a content entity by a unique property.
 */
$load_content_entity = static function (
  string $entity_type,
  string $property,
  string $value,
): ?object {
  $storage = Drupal::entityTypeManager()->getStorage($entity_type);
  $entities = $storage->loadByProperties([$property => $value]);
  return $entities === [] ? NULL : reset($entities);
};

$https_port = getenv('DDEV_ROUTER_HTTPS_PORT') ?: '443';
$demo_sites = [
  [
    'directory' => 'client-one',
    'client_name' => 'Acme Inc.',
    'site_name' => 'Acme Production',
    'environment' => 'prod',
  ],
  [
    'directory' => 'client-two',
    'client_name' => 'Beacon Ltd.',
    'site_name' => 'Beacon Staging',
    'environment' => 'stage',
  ],
];

foreach ($demo_sites as $demo_site) {
  $directory = $demo_site['directory'];
  $state_key = 'hm_core.demo.' . str_replace('-', '_', $directory) . '.agent_secret';
  Drupal::state()->set($state_key, $load_secret($directory));

  $key_id = 'hm_demo_' . str_replace('-', '_', $directory);
  $key = Key::load($key_id) ?? Key::create([
    'id' => $key_id,
    'label' => $demo_site['site_name'] . ' agent key',
    'key_type' => 'authentication',
    'key_provider' => 'state',
    'key_provider_settings' => ['state_key' => $state_key],
  ]);
  $key->save();

  $client = $load_content_entity('hm_client', 'name', $demo_site['client_name']);
  if (!$client instanceof Client) {
    $client = Client::create(['name' => $demo_site['client_name']]);
    $client->save();
  }

  $site = $load_content_entity('hm_site', 'name', $demo_site['site_name']);
  if (!$site instanceof Site) {
    $site = Site::create(['name' => $demo_site['site_name']]);
  }
  $site->set('base_url', sprintf('https://%s.ddev.site:%s', $directory, $https_port));
  $site->set('environment', $demo_site['environment']);
  $site->set('client', $client->id());
  $site->set('agent_key', $key->id());
  $site->set('status', 'active');
  $site->save();

  print sprintf("Monitor demo registered: %s\n", $demo_site['site_name']);
}
