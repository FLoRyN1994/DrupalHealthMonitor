<?php

declare(strict_types=1);

namespace Drupal\hm_core\Report;

/**
 * Generates client-facing text from deterministic report context.
 */
interface ReportTextGeneratorInterface {

  /**
   * Generates and validates report text.
   */
  public function generate(SiteReportContext $context): GeneratedReport;

}
