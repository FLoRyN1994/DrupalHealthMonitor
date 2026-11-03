<?php

declare(strict_types=1);

namespace Drupal\hm_core\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hm_core\SiteCheckRunner;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes queued site status checks.
 */
#[QueueWorker(
  id: 'hm_core_site_check',
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
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly SiteCheckRunner $siteCheckRunner,
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
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!is_array($data) || !is_int($data['site_id'] ?? NULL)) {
      return;
    }

    $this->siteCheckRunner->check($data['site_id']);
  }

}
