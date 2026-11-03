<?php

declare(strict_types=1);

namespace Drupal\hm_agent;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Update\UpdateRegistry;

/**
 * Collects a deterministic snapshot of the client site's Drupal status.
 */
class StatusCollector {

  /**
   * Constructs a status collector.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly UpdateRegistry $postUpdateRegistry,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Collects the status payload.
   *
   * @return array<string, mixed>
   *   The serializable status payload.
   */
  public function collect(): array {
    $modules = [];
    foreach ($this->moduleHandler->getModuleList() as $machine_name => $extension) {
      $modules[$machine_name] = [
        'name' => (string) ($extension->info['name'] ?? $machine_name),
        'version' => (string) ($extension->info['version'] ?? ''),
      ];
    }
    ksort($modules);

    return [
      'schema_version' => 1,
      'generated_at' => $this->time->getCurrentTime(),
      'core_version' => \Drupal::VERSION,
      'php_version' => PHP_VERSION,
      'modules' => $modules,
      'pending_updates' => $this->countPendingUpdates(),
    ];
  }

  /**
   * Counts pending hook_update_N() and post-update functions.
   */
  private function countPendingUpdates(): int {
    require_once DRUPAL_ROOT . '/core/includes/install.inc';
    require_once DRUPAL_ROOT . '/core/includes/update.inc';
    drupal_load_updates();

    $pending_update_count = 0;
    foreach (update_get_update_list() as $update_information) {
      $pending_update_count += count($update_information['pending'] ?? []);
    }

    return $pending_update_count + count($this->postUpdateRegistry->getPendingUpdateFunctions());
  }

}
