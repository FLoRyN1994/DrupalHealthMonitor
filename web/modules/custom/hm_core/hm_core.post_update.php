<?php

/**
 * @file
 * Post-update functions for Health Monitor Core.
 */

declare(strict_types=1);

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
