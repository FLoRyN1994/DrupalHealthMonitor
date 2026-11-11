<?php

/**
 * @file
 * Post-update functions for Health Monitor Core.
 */

declare(strict_types=1);

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\hm_core\Entity\Finding;

/**
 * Ensures uptime storage exists on sites that predate hm_core.install.
 */
function hm_core_post_update_add_uptime_table(): void {
  $database_schema = \Drupal::database()->schema();
  if ($database_schema->tableExists('hm_uptime_check')) {
    return;
  }

  require_once __DIR__ . '/hm_core.install';
  $module_schema = hm_core_schema();
  $database_schema->createTable('hm_uptime_check', $module_schema['hm_uptime_check']);
}

/**
 * Adds evidence source storage to existing Finding entities.
 */
function hm_core_post_update_add_finding_source(): void {
  hm_core_install_finding_source_storage();
}

/**
 * Repairs source storage when the earlier definition-only check skipped it.
 */
function hm_core_post_update_ensure_finding_source_storage(): void {
  hm_core_install_finding_source_storage();
}

/**
 * Installs Finding source storage when its database column is absent.
 */
function hm_core_install_finding_source_storage(): void {
  if (\Drupal::database()->schema()->fieldExists('hm_finding', 'source')) {
    \Drupal::database()->update('hm_finding')
      ->fields(['source' => 'release_data'])
      ->isNull('source')
      ->execute();
    return;
  }

  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_type = $entity_type_manager->getDefinition('hm_finding');
  $definitions = Finding::baseFieldDefinitions($entity_type);
  if (!$definitions['source'] instanceof FieldStorageDefinitionInterface) {
    throw new \UnexpectedValueException('Finding source storage definition is invalid.');
  }
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition(
    'source',
    'hm_finding',
    'hm_core',
    $definitions['source'],
  );
  \Drupal::database()->update('hm_finding')
    ->fields(['source' => 'release_data'])
    ->isNull('source')
    ->execute();
}
