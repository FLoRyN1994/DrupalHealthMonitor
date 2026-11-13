<?php

declare(strict_types=1);

namespace Drupal\hm_core\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\hm_core\Form\DashboardFilterForm;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Symfony\Component\HttpFoundation\RequestStack;

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
    private readonly FormBuilderInterface $formBuilder,
    private readonly RequestStack $requestStack,
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

    $request = $this->requestStack->getCurrentRequest();
    $environment = $request?->query->getString('environment', 'all') ?? 'all';
    $status = $request?->query->getString('status', 'all') ?? 'all';
    $health = $request?->query->getString('health', 'all') ?? 'all';

    $siteQuery = $siteStorage->getQuery()
      ->accessCheck(FALSE)
      ->sort('name');
    if (in_array($environment, ['prod', 'stage', 'dev'], TRUE)) {
      $siteQuery->condition('environment', $environment);
    }
    if (in_array($status, ['active', 'paused', 'disabled'], TRUE)) {
      $siteQuery->condition('status', $status);
    }
    match ($health) {
      'healthy' => $siteQuery->condition('health_score', 90, '>='),
      'attention' => $siteQuery
        ->condition('health_score', 70, '>=')
        ->condition('health_score', 90, '<'),
      'critical' => $siteQuery->condition('health_score', 70, '<'),
      default => $siteQuery,
    };
    $siteIds = $siteQuery->execute();
    $sites = $siteStorage->loadMultiple($siteIds);

    $findingIds = [];
    if ($siteIds !== []) {
      $findingIds = $findingStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('resolved', FALSE)
        ->condition('site', $siteIds, 'IN')
        ->sort('severity')
        ->sort('project_name')
        ->execute();
    }
    $findings = $findingStorage->loadMultiple($findingIds);

    $findingCounts = [];
    $findingRows = [];
    $urgentFindingCount = 0;
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
      if (in_array((string) $finding->get('severity')->value, ['critical', 'high'], TRUE)) {
        $urgentFindingCount++;
      }
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
    $healthScoreTotal = 0;
    foreach ($sites as $site) {
      if (!$site instanceof Site) {
        continue;
      }

      $healthScoreTotal += (int) $site->get('health_score')->value;

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
        'contexts' => [
          'url.query_args:environment',
          'url.query_args:status',
          'url.query_args:health',
        ],
        'tags' => ['hm_site_list', 'hm_finding_list'],
      ],
      'intro' => [
        '#markup' => '<div class="hm-dashboard__intro"><p>Fleet operations</p><span>Health, open findings, upgrade risks, and maintenance priorities in one place.</span></div>',
      ],
      'summary' => [
        '#type' => 'component',
        '#component' => 'hm_theme:fleet-summary',
        '#props' => [
          'site_count' => count($sites),
          'average_health' => $sites === [] ? 0 : (int) round($healthScoreTotal / count($sites)),
          'open_findings' => count($findings),
          'urgent_findings' => $urgentFindingCount,
        ],
      ],
      'filters' => $this->formBuilder->getForm(DashboardFilterForm::class),
      'results' => [
        '#type' => 'container',
        '#attributes' => [
          'id' => 'hm-dashboard-results',
          'class' => ['hm-dashboard__results'],
        ],
        'sites' => [
          '#type' => 'container',
          '#attributes' => ['class' => ['hm-dashboard__sites']],
          'cards' => $siteCards ?: [
            '#markup' => '<p>No monitored sites match the selected filters.</p>',
          ],
        ],
        'findings' => [
          '#type' => 'component',
          '#component' => 'hm_theme:findings-table',
          '#props' => [
            'caption' => 'Open findings for the selected sites',
            'findings' => $findingRows,
          ],
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
