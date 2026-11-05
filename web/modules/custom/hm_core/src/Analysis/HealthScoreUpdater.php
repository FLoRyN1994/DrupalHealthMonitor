<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;

/**
 * Recalculates and persists one site's health score.
 */
final class HealthScoreUpdater {

  /**
   * Number of recent uptime results included in the score.
   */
  private const UPTIME_WINDOW = 12;

  /**
   * Constructs the score updater.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly HealthScoreCalculator $calculator,
  ) {
  }

  /**
   * Recalculates one monitored site's score from current persisted evidence.
   */
  public function update(int $siteId): void {
    $site = $this->entityTypeManager->getStorage('hm_site')->load($siteId);
    if (!$site instanceof Site) {
      return;
    }

    $finding_storage = $this->entityTypeManager->getStorage('hm_finding');
    $finding_ids = $finding_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $siteId)
      ->condition('resolved', FALSE)
      ->execute();
    $finding_types = [];
    foreach ($finding_storage->loadMultiple($finding_ids) as $finding) {
      if ($finding instanceof Finding) {
        $finding_types[] = (string) $finding->get('type')->value;
      }
    }

    $query = $this->database->select('hm_uptime_check', 'uptime');
    $query->addField('uptime', 'success');
    $uptime_values = $query
      ->condition('site_id', $siteId)
      ->orderBy('checked_at', 'DESC')
      ->orderBy('id', 'DESC')
      ->range(0, self::UPTIME_WINDOW)
      ->execute()
      ->fetchCol();
    $uptime_results = array_map(
      static fn (mixed $success): bool => (bool) $success,
      $uptime_values,
    );

    $score = $this->calculator->calculate($finding_types, $uptime_results);
    if ((int) $site->get('health_score')->value !== $score) {
      $site->set('health_score', $score)->save();
    }
  }

}
