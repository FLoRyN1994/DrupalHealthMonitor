<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Entity\Snapshot;

/**
 * Converts fresh Drupal 12 Upgrade Status evidence into findings.
 */
final class UpgradeStatusAnalyzer {

  /**
   * Constructs the Upgrade Status analyzer.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FindingReconciler $findingReconciler,
  ) {
  }

  /**
   * Reconciles findings explicitly covered by fresh scan results.
   */
  public function analyze(Snapshot $snapshot): void {
    try {
      $payload = json_decode(
        (string) $snapshot->get('raw_payload')->value,
        TRUE,
        512,
        JSON_THROW_ON_ERROR,
      );
      $modules = json_decode(
        (string) $snapshot->get('modules')->value,
        TRUE,
        512,
        JSON_THROW_ON_ERROR,
      );
    }
    catch (\JsonException) {
      return;
    }
    if (!is_array($payload)
      || !is_array($modules)
      || !is_array($payload['upgrade_status'] ?? NULL)
    ) {
      return;
    }

    $upgrade_status = $payload['upgrade_status'];
    if (($upgrade_status['schema_version'] ?? NULL) !== 1
      || ($upgrade_status['status'] ?? NULL) !== 'available'
      || ($upgrade_status['target_major'] ?? NULL) !== 12
      || !is_array($upgrade_status['projects'] ?? NULL)
    ) {
      return;
    }

    $versions = $this->projectVersions($modules);
    $site_id = (int) $snapshot->get('site')->target_id;
    foreach ($upgrade_status['projects'] as $project) {
      if (!is_array($project)
        || !is_string($project['project'] ?? NULL)
        || preg_match('/^[a-z0-9_]{1,128}$/', $project['project']) !== 1
        || !is_bool($project['stale'] ?? NULL)
        || $project['stale']
        || !is_int($project['errors'] ?? NULL)
        || $project['errors'] < 0
      ) {
        continue;
      }

      $project_name = $project['project'];
      $candidates = [];
      if ($project['errors'] > 0
        && !$this->hasReleaseDataBlocker($site_id, $project_name)
      ) {
        $candidates['d12_blocker'] = new FindingCandidate(
          'd12_blocker',
          'high',
          $versions[$project_name] ?? '',
          '',
        );
      }
      $this->findingReconciler->reconcile(
        $site_id,
        $project_name,
        $candidates,
        'upgrade_status',
      );
    }
  }

  /**
   * Maps contributed project names to installed versions.
   *
   * @param array<mixed> $modules
   *   Normalized module payload.
   *
   * @return array<string, string>
   *   Installed versions keyed by drupal.org project.
   */
  private function projectVersions(array $modules): array {
    $versions = [];
    foreach ($modules as $module) {
      if (is_array($module)
        && is_string($module['project'] ?? NULL)
        && is_string($module['version'] ?? NULL)
      ) {
        $versions[$module['project']] = $module['version'];
      }
    }
    return $versions;
  }

  /**
   * Returns whether release data already explains this project's blocker.
   */
  private function hasReleaseDataBlocker(
    int $siteId,
    string $projectName,
  ): bool {
    $ids = $this->entityTypeManager->getStorage('hm_finding')->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $siteId)
      ->condition('project_name', $projectName)
      ->condition('type', 'd12_blocker')
      ->condition('source', 'release_data')
      ->condition('resolved', FALSE)
      ->range(0, 1)
      ->execute();
    return $ids !== [];
  }

}
