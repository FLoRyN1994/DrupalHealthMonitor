<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Queues all active monitored sites for collection.
 */
final class SiteCheckScheduler {

  /**
   * Queue processed by the site-check worker.
   */
  public const QUEUE_NAME = 'hm_core_site_check';

  /**
   * Seconds after which a pending marker expires.
   *
   * Markers are removed as soon as a check is processed. The expiry only
   * guarantees that an item lost from the queue (or a crashed worker) cannot
   * stop a site from being checked again for longer than three cron cycles.
   */
  private const PENDING_TTL = 900;

  /**
   * Constructs a site-check scheduler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
    private readonly KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
  ) {
  }

  /**
   * Adds one queue item for each active site without a pending check.
   *
   * Cron runs every five minutes, but a slow or unreachable site can take up
   * to 20 seconds to check. Skipping sites that still have a queued item keeps
   * the queue from growing without bound when the worker falls behind.
   *
   * @return int
   *   The number of newly queued sites.
   */
  public function enqueueActiveSites(): int {
    $site_ids = $this->entityTypeManager
      ->getStorage('hm_site')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'active')
      ->execute();
    if ($site_ids === []) {
      return 0;
    }

    $pending = $this->pendingStore();
    $pending_ids = $pending->getMultiple(array_map('strval', $site_ids));
    $queue = $this->queueFactory->get(self::QUEUE_NAME);
    $queued = 0;
    foreach ($site_ids as $site_id) {
      if (array_key_exists((string) $site_id, $pending_ids)) {
        continue;
      }
      $queue->createItem(['site_id' => (int) $site_id]);
      $pending->setWithExpire((string) $site_id, TRUE, self::PENDING_TTL);
      $queued++;
    }

    return $queued;
  }

  /**
   * Clears a site's pending marker after its queued check has run.
   */
  public function markProcessed(int $siteId): void {
    $this->pendingStore()->delete((string) $siteId);
  }

  /**
   * Returns the store of sites that already have a queued check.
   */
  private function pendingStore(): KeyValueStoreExpirableInterface {
    return $this->keyValueExpirableFactory->get('hm_core.site_check_pending');
  }

}
