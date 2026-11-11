<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

use Drupal\hm_core\Entity\Snapshot;
use Drupal\hm_core\Release\ReleaseDataClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates deterministic analysis of a persisted site snapshot.
 */
final class SnapshotAnalyzer {

  /**
   * Constructs the snapshot analyzer.
   */
  public function __construct(
    private readonly ReleaseDataClientInterface $releaseDataClient,
    private readonly ProjectAnalyzer $projectAnalyzer,
    private readonly FindingReconciler $findingReconciler,
    private readonly UpgradeStatusAnalyzer $upgradeStatusAnalyzer,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * Analyzes core and all identifiable contributed projects.
   */
  public function analyze(Snapshot $snapshot): void {
    $site_id = (int) $snapshot->get('site')->target_id;
    $core_version = (string) $snapshot->get('core_version')->value;
    $projects = ['drupal' => $core_version];
    $has_complete_provenance = TRUE;

    try {
      $modules = json_decode(
        (string) $snapshot->get('modules')->value,
        TRUE,
        512,
        JSON_THROW_ON_ERROR,
      );
    }
    catch (\JsonException $exception) {
      $this->logger->warning('Snapshot @snapshot has invalid module JSON: @message', [
        '@snapshot' => $snapshot->id(),
        '@message' => $exception->getMessage(),
      ]);
      return;
    }

    if (!is_array($modules)) {
      return;
    }
    foreach ($modules as $module) {
      if (!is_array($module) || !is_string($module['origin'] ?? NULL)) {
        $has_complete_provenance = FALSE;
        continue;
      }
      if ($module['origin'] !== 'contrib') {
        continue;
      }
      $project = $module['project'] ?? NULL;
      $version = $module['version'] ?? NULL;
      if (!is_string($project) || $project === ''
        || !is_string($version) || $version === ''
      ) {
        $has_complete_provenance = FALSE;
        continue;
      }
      $projects[$project] = $version;
    }

    foreach ($projects as $project_name => $installed_version) {
      $result = $this->releaseDataClient->getProject($project_name);
      if (!$result->isAvailable() || $result->project === NULL) {
        continue;
      }
      $candidates = $this->projectAnalyzer->analyze(
        $result->project,
        $installed_version,
        $core_version,
        $project_name !== 'drupal',
      );
      $this->findingReconciler->reconcile(
        $site_id,
        $project_name,
        $candidates,
      );
    }

    if ($has_complete_provenance) {
      $this->findingReconciler->resolveRemovedProjects(
        $site_id,
        array_keys($projects),
      );
    }
    $this->upgradeStatusAnalyzer->analyze($snapshot);
  }

}
