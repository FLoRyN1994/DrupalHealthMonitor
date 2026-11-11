<?php

declare(strict_types=1);

namespace Drupal\hm_agent;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

/**
 * Normalizes optional Upgrade Status scan results without depending on it.
 */
final class UpgradeStatusCollector {

  /**
   * Maximum age at which scan evidence is considered current.
   */
  private const MAX_AGE = 2_592_000;

  /**
   * Maximum project results included in one agent response.
   */
  private const MAX_PROJECTS = 250;

  /**
   * Maximum normalized issue count accepted per category.
   */
  private const MAX_ISSUES = 100_000;

  /**
   * Constructs the Upgrade Status collector.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
    private readonly KeyValueFactoryInterface $keyValueFactory,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Returns a bounded, versioned summary of saved scan results.
   *
   * @return array<string, mixed>
   *   Normalized Upgrade Status state.
   */
  public function collect(): array {
    $now = $this->time->getCurrentTime();
    $base = [
      'schema_version' => 1,
      'status' => 'not_installed',
      'target_major' => ((int) explode('.', \Drupal::VERSION)[0]) + 1,
      'collected_at' => $now,
      'projects' => [],
    ];
    if (!$this->moduleHandler->moduleExists('upgrade_status')) {
      return $base;
    }

    $stored_results = $this->keyValueFactory
      ->get('upgrade_status_scan_results')
      ->getAll();
    if ($stored_results === []) {
      $base['status'] = 'never_scanned';
      return $base;
    }

    $projects = [];
    $invalid_results = 0;
    foreach (array_slice($stored_results, 0, self::MAX_PROJECTS, TRUE) as $project => $result) {
      $normalized = $this->normalizeResult($project, $result, $now);
      if ($normalized === NULL) {
        $invalid_results++;
        continue;
      }
      $projects[] = $normalized;
    }

    $base['projects'] = $projects;
    if ($projects === []) {
      $base['status'] = $invalid_results > 0 ? 'failed' : 'never_scanned';
      return $base;
    }
    $base['status'] = array_filter(
      $projects,
      static fn (array $project): bool => !$project['stale'],
    ) === [] ? 'stale' : 'available';
    return $base;
  }

  /**
   * Normalizes one saved Upgrade Status result.
   *
   * @return array<string, int|string|bool>|null
   *   Normalized result, or NULL for malformed evidence.
   */
  private function normalizeResult(
    mixed $project,
    mixed $result,
    int $now,
  ): ?array {
    if (!is_string($project)
      || preg_match('/^[a-z0-9_]{1,128}$/', $project) !== 1
      || !is_array($result)
      || !is_int($result['date'] ?? NULL)
      || $result['date'] <= 0
      || $result['date'] > $now + 300
      || !is_array($result['data']['totals'] ?? NULL)
    ) {
      return NULL;
    }

    $totals = $result['data']['totals'];
    $split = $totals['upgrade_status_split'] ?? [];
    if (!is_array($split)) {
      return NULL;
    }
    $errors = $this->issueCount($split['error'] ?? 0);
    $warnings = $this->issueCount($split['warning'] ?? 0);
    $total_issues = $this->issueCount($totals['file_errors'] ?? 0);
    if ($errors === NULL || $warnings === NULL || $total_issues === NULL) {
      return NULL;
    }
    $next_action = $totals['upgrade_status_next'] ?? '';
    if (!is_string($next_action) || !in_array($next_action, [
      'relax',
      'remove',
      'update',
      'collaborate',
      'scan',
      'rector',
      'manual',
    ], TRUE)) {
      return NULL;
    }

    return [
      'project' => $project,
      'scanned_at' => $result['date'],
      'stale' => $result['date'] < $now - self::MAX_AGE,
      'errors' => $errors,
      'warnings' => $warnings,
      'total_issues' => $total_issues,
      'next_action' => $next_action,
    ];
  }

  /**
   * Validates and bounds an issue count.
   */
  private function issueCount(mixed $count): ?int {
    return is_int($count) && $count >= 0 && $count <= self::MAX_ISSUES
      ? $count
      : NULL;
  }

}
