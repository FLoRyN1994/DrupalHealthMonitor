<?php

declare(strict_types=1);

namespace Drupal\hm_core\Report;

/**
 * Deterministic site data supplied to an AI report writer.
 */
final readonly class SiteReportContext {

  /**
   * Creates report context.
   *
   * @param string $siteName
   *   The monitored site label.
   * @param string $baseUrl
   *   The monitored site URL.
   * @param string $environment
   *   The site environment machine name.
   * @param int $healthScore
   *   The deterministic health score.
   * @param float $effortHours
   *   The deterministic effort estimate.
   * @param \Drupal\hm_core\Report\ReportFinding[] $findings
   *   Open findings for the site.
   */
  public function __construct(
    public string $siteName,
    public string $baseUrl,
    public string $environment,
    public int $healthScore,
    public float $effortHours,
    public array $findings,
  ) {}

  /**
   * Returns prompt-safe scalar data.
   *
   * @return array<string, mixed>
   *   Site report data.
   */
  public function toArray(): array {
    return [
      'site_name' => $this->siteName,
      'base_url' => $this->baseUrl,
      'environment' => $this->environment,
      'health_score' => $this->healthScore,
      'deterministic_effort_hours' => $this->effortHours,
      'open_findings' => array_map(
        static fn (ReportFinding $finding): array => $finding->toArray(),
        $this->findings,
      ),
    ];
  }

}
