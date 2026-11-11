<?php

declare(strict_types=1);

namespace Drupal\hm_core\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;

/**
 * Builds the fleet health dashboard.
 */
final class DashboardController implements ContainerInjectionInterface {

  use AutowireTrait;

  /**
   * Creates a dashboard controller.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Builds the dashboard from current Site and Finding entities.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function build(): array {
    $siteStorage = $this->entityTypeManager->getStorage('hm_site');
    $findingStorage = $this->entityTypeManager->getStorage('hm_finding');

    $siteIds = $siteStorage->getQuery()
      ->accessCheck(FALSE)
      ->sort('name')
      ->execute();
    $sites = $siteStorage->loadMultiple($siteIds);

    $findingIds = $findingStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('resolved', FALSE)
      ->sort('severity')
      ->sort('project_name')
      ->execute();
    $findings = $findingStorage->loadMultiple($findingIds);

    $findingCounts = [];
    $findingRows = [];
    foreach ($findings as $finding) {
      if (!$finding instanceof Finding) {
        continue;
      }

      $site = $finding->get('site')->entity;
      if (!$site instanceof Site) {
        continue;
      }

      $siteId = (int) $site->id();
      $findingCounts[$siteId] = ($findingCounts[$siteId] ?? 0) + 1;
      $findingRows[] = [
        'site_name' => (string) $site->label(),
        'site_url' => $site->toUrl()->toString(),
        'project_name' => (string) $finding->label(),
        'type' => $this->fieldLabel($finding, 'type'),
        'severity' => (string) $finding->get('severity')->value,
        'current_version' => (string) ($finding->get('current_version')->value ?? ''),
        'recommended_version' => (string) ($finding->get('recommended_version')->value ?? ''),
      ];
    }

    $siteCards = [];
    foreach ($sites as $site) {
      if (!$site instanceof Site) {
        continue;
      }

      $siteCard = [
        '#type' => 'component',
        '#component' => 'hm_theme:site-card',
        '#props' => [
          'name' => (string) $site->label(),
          'base_url' => (string) $site->get('base_url')->value,
          'environment' => $this->fieldLabel($site, 'environment'),
          'status' => $this->fieldLabel($site, 'status'),
          'health_score' => (int) $site->get('health_score')->value,
          'open_findings' => $findingCounts[(int) $site->id()] ?? 0,
          'detail_url' => $site->toUrl()->toString(),
        ],
      ];
      CacheableMetadata::createFromObject($site)->applyTo($siteCard);
      $siteCards[] = $siteCard;
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['hm-dashboard']],
      '#cache' => [
        'tags' => ['hm_site_list', 'hm_finding_list'],
      ],
      'intro' => [
        '#markup' => '<p class="hm-dashboard__intro">Fleet health, open findings, and upgrade risks in one place.</p>',
      ],
      'sites' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['hm-dashboard__sites']],
        'cards' => $siteCards ?: [
          '#markup' => '<p>No monitored sites have been added yet.</p>',
        ],
      ],
      'findings' => [
        '#type' => 'component',
        '#component' => 'hm_theme:findings-table',
        '#props' => [
          'caption' => 'Open findings across the fleet',
          'findings' => $findingRows,
        ],
      ],
    ];

    foreach ($findings as $finding) {
      CacheableMetadata::createFromObject($finding)->applyTo($build);
    }

    return $build;
  }

  /**
   * Gets the configured display label for a list field value.
   */
  private function fieldLabel(Site|Finding $entity, string $fieldName): string {
    $value = (string) $entity->get($fieldName)->value;
    $allowedValues = $entity->getFieldDefinition($fieldName)
      ->getSetting('allowed_values');

    return (string) ($allowedValues[$value] ?? $value);
  }

}
