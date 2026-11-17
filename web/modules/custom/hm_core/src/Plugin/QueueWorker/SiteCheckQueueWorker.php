<?php

declare(strict_types=1);

namespace Drupal\hm_core\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hm_core\SiteCheckRunner;
use Drupal\hm_core\SiteCheckScheduler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued site status checks.
 */
#[QueueWorker(
  id: SiteCheckScheduler::QUEUE_NAME,
  title: new TranslatableMarkup('Health Monitor site check'),
  cron: ['time' => 60],
)]
final class SiteCheckQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a site-check queue worker.
   *
   * @param array<mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\hm_core\SiteCheckRunner $siteCheckRunner
   *   Site-check runner service.
   * @param \Drupal\hm_core\SiteCheckScheduler $siteCheckScheduler
   *   Scheduler that tracks pending checks.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly SiteCheckRunner $siteCheckRunner,
    protected readonly SiteCheckScheduler $siteCheckScheduler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Service container.
   * @param array<mixed> $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(SiteCheckRunner::class),
      $container->get(SiteCheckScheduler::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!is_array($data) || !is_int($data['site_id'] ?? NULL)) {
      return;
    }

    try {
      $this->siteCheckRunner->check($data['site_id']);
    }
    finally {
      // Clear the marker even on failure: the next cron run should be free to
      // queue a fresh check instead of waiting for the marker to expire.
      $this->siteCheckScheduler->markProcessed($data['site_id']);
    }
  }

}
