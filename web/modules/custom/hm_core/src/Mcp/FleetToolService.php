<?php

declare(strict_types=1);

namespace Drupal\hm_core\Mcp;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Entity\AiReport;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Report\SiteReportContextBuilder;
use Drupal\hm_core\Report\SiteReportService;

/**
 * Provides deterministic fleet data to MCP plugins.
 */
final class FleetToolService {

  /**
   * Creates the fleet tool service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly SiteReportContextBuilder $contextBuilder,
    private readonly SiteReportService $reportService,
  ) {}

  /**
   * Lists monitored sites with health and open-work totals.
   *
   * @return array<int, array<string, int|float|string>>
   *   Fleet site records.
   */
  public function listSites(): array {
    $siteStorage = $this->entityTypeManager->getStorage('hm_site');
    $siteIds = $siteStorage->getQuery()
      ->accessCheck(FALSE)
      ->sort('name')
      ->execute();

    $sites = [];
    foreach ($siteStorage->loadMultiple($siteIds) as $site) {
      if (!$site instanceof Site) {
        continue;
      }
      $context = $this->contextBuilder->build($site);
      $securityCount = count(array_filter(
        $context->findings,
        static fn ($finding): bool => $finding->type === 'security',
      ));
      $sites[] = [
        'site_id' => (int) $site->id(),
        'name' => (string) $site->label(),
        'base_url' => (string) $site->get('base_url')->value,
        'environment' => (string) $site->get('environment')->value,
        'status' => (string) $site->get('status')->value,
        'health_score' => (int) $site->get('health_score')->value,
        'open_findings' => count($context->findings),
        'security_findings' => $securityCount,
        'estimated_effort_hours' => $context->effortHours,
      ];
    }

    return $sites;
  }

  /**
   * Returns all open findings for one monitored site.
   *
   * @return array<string, mixed>
   *   Site and finding data.
   */
  public function getSiteFindings(int $siteId): array {
    $site = $this->loadSite($siteId);
    $findingStorage = $this->entityTypeManager->getStorage('hm_finding');
    $findingIds = $findingStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('site', $site->id())
      ->condition('resolved', FALSE)
      ->sort('severity')
      ->sort('project_name')
      ->execute();

    $findings = [];
    $totalHours = 0.0;
    foreach ($findingStorage->loadMultiple($findingIds) as $finding) {
      if (!$finding instanceof Finding) {
        continue;
      }
      $serialized = $this->serializeFinding($finding);
      $totalHours += $serialized['estimated_effort_hours'];
      $findings[] = $serialized;
    }

    return [
      'site_id' => (int) $site->id(),
      'site_name' => (string) $site->label(),
      'health_score' => (int) $site->get('health_score')->value,
      'open_finding_count' => count($findings),
      'estimated_effort_hours' => round($totalHours, 2),
      'findings' => $findings,
    ];
  }

  /**
   * Returns all open security updates across the fleet.
   *
   * @return array<string, mixed>
   *   Security update data and deterministic total effort.
   */
  public function getSecurityUpdates(): array {
    $findingStorage = $this->entityTypeManager->getStorage('hm_finding');
    $findingIds = $findingStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'security')
      ->condition('resolved', FALSE)
      ->sort('severity')
      ->sort('project_name')
      ->execute();

    $updates = [];
    $totalHours = 0.0;
    foreach ($findingStorage->loadMultiple($findingIds) as $finding) {
      if (!$finding instanceof Finding) {
        continue;
      }
      $site = $finding->get('site')->entity;
      if (!$site instanceof Site) {
        continue;
      }
      $serialized = $this->serializeFinding($finding);
      $serialized['site_id'] = (int) $site->id();
      $serialized['site_name'] = (string) $site->label();
      $totalHours += $serialized['estimated_effort_hours'];
      $updates[] = $serialized;
    }

    return [
      'security_update_count' => count($updates),
      'estimated_effort_hours' => round($totalHours, 2),
      'updates' => $updates,
    ];
  }

  /**
   * Generates and persists a new report version for one site.
   *
   * @return array<string, int|float|string>
   *   Generated report data.
   */
  public function generateSiteReport(int $siteId): array {
    $report = $this->reportService->generate($this->loadSite($siteId));

    return $this->serializeReport($report);
  }

  /**
   * Loads a monitored site or rejects an unknown identifier.
   */
  private function loadSite(int $siteId): Site {
    if ($siteId < 1) {
      throw new \InvalidArgumentException('site_id must be a positive integer.');
    }
    $site = $this->entityTypeManager->getStorage('hm_site')->load($siteId);
    if (!$site instanceof Site) {
      throw new \InvalidArgumentException(sprintf('Monitored site %d was not found.', $siteId));
    }

    return $site;
  }

  /**
   * Serializes one finding with its deterministic effort estimate.
   *
   * @return array<string, float|int|string>
   *   Finding data.
   */
  private function serializeFinding(Finding $finding): array {
    $type = (string) $finding->get('type')->value;
    $severity = (string) $finding->get('severity')->value;

    return [
      'finding_id' => (int) $finding->id(),
      'project_name' => (string) $finding->label(),
      'type' => $type,
      'severity' => $severity,
      'evidence_source' => (string) $finding->get('source')->value,
      'current_version' => (string) ($finding->get('current_version')->value ?? ''),
      'recommended_version' => (string) ($finding->get('recommended_version')->value ?? ''),
      'estimated_effort_hours' => $this->contextBuilder->estimateFindingEffort($type, $severity),
    ];
  }

  /**
   * Serializes a persisted AI report.
   *
   * @return array<string, int|float|string>
   *   Report data.
   */
  private function serializeReport(AiReport $report): array {
    return [
      'report_id' => (int) $report->id(),
      'site_id' => (int) $report->get('site')->target_id,
      'title' => (string) $report->label(),
      'generated_at' => (int) $report->get('generated_at')->value,
      'summary' => (string) $report->get('summary_text')->value,
      'release_digest' => (string) $report->get('release_digest')->value,
      'd12_advice' => (string) $report->get('d12_advice')->value,
      'estimated_effort_hours' => (float) $report->get('effort_estimate')->value,
      'model_used' => (string) $report->get('model_used')->value,
    ];
  }

}
