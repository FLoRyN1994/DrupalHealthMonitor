<?php

declare(strict_types=1);

namespace Drupal\hm_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\hm_core\SiteCheckScheduler;

/**
 * Schedules monitored site collection during cron.
 */
final class CronHooks {

  /**
   * Constructs the cron hooks.
   */
  public function __construct(
    private readonly SiteCheckScheduler $siteCheckScheduler,
  ) {
  }

  /**
   * Queues all active monitored sites.
   */
  #[Hook('cron')]
  public function cron(): void {
    $this->siteCheckScheduler->enqueueActiveSites();
  }

}
