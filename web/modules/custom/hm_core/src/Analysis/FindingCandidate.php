<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

/**
 * Immutable evidence for one finding that should be open.
 */
final readonly class FindingCandidate {

  /**
   * Constructs a finding candidate.
   */
  public function __construct(
    public string $type,
    public string $severity,
    public string $currentVersion,
    public string $recommendedVersion,
  ) {
  }

}
