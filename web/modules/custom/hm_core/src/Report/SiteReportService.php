<?php

declare(strict_types=1);

namespace Drupal\hm_core\Report;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Entity\AiReport;
use Drupal\hm_core\Entity\Site;

/**
 * Generates and persists versioned site reports.
 */
final class SiteReportService {

  /**
   * Creates the report service.
   */
  public function __construct(
    private readonly SiteReportContextBuilder $contextBuilder,
    private readonly ReportTextGeneratorInterface $textGenerator,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly TimeInterface $time,
  ) {}

  /**
   * Generates and saves a new report version for a site.
   */
  public function generate(Site $site): AiReport {
    $context = $this->contextBuilder->build($site);
    $generated = $this->textGenerator->generate($context);
    $timestamp = $this->time->getCurrentTime();
    $report = $this->entityTypeManager->getStorage('hm_ai_report')->create([
      'title' => sprintf('%s maintenance report — %s', $site->label(), gmdate('Y-m-d', $timestamp)),
      'site' => $site->id(),
      'generated_at' => $timestamp,
      'summary_text' => ['value' => $generated->summary, 'format' => 'plain_text'],
      'release_digest' => ['value' => $generated->releaseDigest, 'format' => 'plain_text'],
      'd12_advice' => ['value' => $generated->d12Advice, 'format' => 'plain_text'],
      'effort_estimate' => number_format($context->effortHours, 2, '.', ''),
      'model_used' => $generated->modelUsed,
    ]);
    if (!$report instanceof AiReport) {
      throw new ReportGenerationException('AI report storage returned an unexpected entity.');
    }
    $report->save();

    return $report;
  }

}
