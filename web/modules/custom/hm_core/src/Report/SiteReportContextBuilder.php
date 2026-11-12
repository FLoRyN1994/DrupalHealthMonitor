<?php

declare(strict_types=1);

namespace Drupal\hm_core\Report;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;

/**
 * Builds deterministic report context and effort estimates.
 */
final class SiteReportContextBuilder {

  /**
   * Creates a context builder.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Builds report context for one site.
   */
  public function build(Site $site): SiteReportContext {
    $findingStorage = $this->entityTypeManager->getStorage('hm_finding');
    $findingIds = $findingStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $site->id())
      ->condition('resolved', FALSE)
      ->sort('severity')
      ->sort('project_name')
      ->execute();

    $reportFindings = [];
    $effortHours = 0.0;
    foreach ($findingStorage->loadMultiple($findingIds) as $finding) {
      if (!$finding instanceof Finding) {
        continue;
      }

      $type = (string) $finding->get('type')->value;
      $severity = (string) $finding->get('severity')->value;
      $reportFindings[] = new ReportFinding(
        projectName: (string) $finding->label(),
        type: $type,
        severity: $severity,
        source: (string) $finding->get('source')->value,
        currentVersion: (string) ($finding->get('current_version')->value ?? ''),
        recommendedVersion: (string) ($finding->get('recommended_version')->value ?? ''),
      );
      $effortHours += $this->estimateFindingEffort($type, $severity);
    }

    return new SiteReportContext(
      siteName: (string) $site->label(),
      baseUrl: (string) $site->get('base_url')->value,
      environment: (string) $site->get('environment')->value,
      healthScore: (int) $site->get('health_score')->value,
      effortHours: round($effortHours, 2),
      findings: $reportFindings,
    );
  }

  /**
   * Calculates deterministic effort for one finding.
   */
  public function estimateFindingEffort(string $type, string $severity): float {
    $baseHours = match ($type) {
      'security' => 2.0,
      'unsupported' => 3.0,
      'd12_blocker' => 2.0,
      'update' => 0.75,
      default => 0.5,
    };
    $severityHours = match ($severity) {
      'critical' => 1.0,
      'high' => 0.5,
      default => 0.0,
    };

    return $baseHours + $severityHours;
  }

}
