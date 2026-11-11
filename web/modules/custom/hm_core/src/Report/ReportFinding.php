<?php

declare(strict_types=1);

namespace Drupal\hm_core\Report;

/**
 * Immutable finding data supplied to report generation.
 */
final readonly class ReportFinding {

  /**
   * Creates report finding data.
   */
  public function __construct(
    public string $projectName,
    public string $type,
    public string $severity,
    public string $source,
    public string $currentVersion,
    public string $recommendedVersion,
  ) {}

  /**
   * Returns prompt-safe scalar data.
   *
   * @return array<string, string>
   *   Finding data.
   */
  public function toArray(): array {
    return [
      'project_name' => $this->projectName,
      'type' => $this->type,
      'severity' => $this->severity,
      'evidence_source' => $this->source,
      'current_version' => $this->currentVersion,
      'recommended_version' => $this->recommendedVersion,
    ];
  }

}
