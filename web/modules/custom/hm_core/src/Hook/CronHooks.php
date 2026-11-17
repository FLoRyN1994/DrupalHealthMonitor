<?php

declare(strict_types=1);

namespace Drupal\hm_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\hm_core\DataRetention;
use Drupal\hm_core\SiteCheckScheduler;

/**
 * Schedules monitored site collection and purges old history during cron.
 */
final class CronHooks {

  /**
   * Constructs the cron hooks.
   */
  public function __construct(
    private readonly SiteCheckScheduler $siteCheckScheduler,
    private readonly DataRetention $dataRetention,
  ) {
  }

  /**
   * Queues active monitored sites and purges expired history.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->siteCheckScheduler->enqueueActiveSites();
    $this->dataRetention->purge();
  }

}
