<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Purges collected history that is older than the retention windows.
 *
 * Every active site produces one snapshot and one uptime row per check, so
 * without pruning a fleet grows by hundreds of rows per site each day.
 */
final class DataRetention {

  /**
   * Uptime rows older than this are deleted (90 days).
   *
   * The health score only uses the last 30 days; 90 days keeps room for
   * quarterly reporting.
   */
  public const UPTIME_RETENTION = 7_776_000;

  /**
   * Snapshots older than this are deleted (30 days).
   *
   * The latest snapshot for each site is always kept.
   */
  public const SNAPSHOT_RETENTION = 2_592_000;

  /**
   * Maximum snapshots deleted per run, keeping cron runs short.
   */
  public const SNAPSHOT_BATCH = 200;

  /**
   * Constructs the data retention service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Deletes expired uptime rows and snapshots.
   *
   * @return array{uptime: int, snapshots: int}
   *   The number of deleted uptime rows and snapshots.
   */
  public function purge(): array {
    $now = $this->time->getRequestTime();

    $deletedUptime = (int) $this->database->delete('hm_uptime_check')
      ->condition('checked_at', $now - self::UPTIME_RETENTION, '<')
      ->execute();

    return [
      'uptime' => $deletedUptime,
      'snapshots' => $this->purgeSnapshots($now - self::SNAPSHOT_RETENTION),
    ];
  }

  /**
   * Deletes one batch of expired snapshots, keeping each site's latest.
   */
  private function purgeSnapshots(int $cutoff): int {
    $storage = $this->entityTypeManager->getStorage('hm_snapshot');
    $candidateIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('checked_at', $cutoff, '<')
      ->sort('id')
      ->range(0, self::SNAPSHOT_BATCH)
      ->execute();
    if ($candidateIds === []) {
      return 0;
    }

    // The newest snapshot per site may be old when a site has been paused.
    // It still backs the site's current module inventory, so keep it.
    $latestQuery = $this->database->select('hm_snapshot', 'snapshot');
    $latestQuery->addExpression('MAX(snapshot.id)', 'latest_id');
    $latestQuery->groupBy('snapshot.site');
    $latestIds = array_map('intval', $latestQuery->execute()->fetchCol());

    $deleteIds = array_diff(array_map('intval', $candidateIds), $latestIds);
    if ($deleteIds === []) {
      return 0;
    }
    $storage->delete($storage->loadMultiple($deleteIds));

    return count($deleteIds);
  }

}
