<?php

declare(strict_types=1);

namespace Drupal\hm_core\Report;

/**
 * Validated text generated for a maintenance report.
 */
final readonly class GeneratedReport {

  /**
   * Creates generated report text.
   */
  public function __construct(
    public string $summary,
    public string $releaseDigest,
    public string $d12Advice,
    public string $modelUsed,
  ) {}

}
