<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Queues all active monitored sites for collection.
 */
final class SiteCheckScheduler {

  /**
   * Constructs a site-check scheduler.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QueueFactory $queueFactory,
  ) {
  }

  /**
   * Adds one queue item for each active site.
   *
   * @return int
   *   The number of queued sites.
   */
  public function enqueueActiveSites(): int {
    $site_ids = $this->entityTypeManager
      ->getStorage('hm_site')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 'active')
      ->execute();

    $queue = $this->queueFactory->get('hm_core_site_check');
    foreach ($site_ids as $site_id) {
      $queue->createItem(['site_id' => (int) $site_id]);
    }

    return count($site_ids);
  }

}
