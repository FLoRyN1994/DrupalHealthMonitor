<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

/**
 * Calculates an explainable site health score from findings and uptime.
 */
final class HealthScoreCalculator {

  /**
   * Finding penalty and cap by type.
   */
  private const FINDING_PENALTIES = [
    'security' => ['weight' => 30, 'cap' => 60],
    'unsupported' => ['weight' => 15, 'cap' => 30],
    'd12_blocker' => ['weight' => 10, 'cap' => 20],
    'update' => ['weight' => 5, 'cap' => 20],
  ];

  /**
   * Maximum penalty contributed by recent uptime failures.
   */
  private const UPTIME_PENALTY_CAP = 30;

  /**
   * Calculates a score from 0 to 100.
   *
   * @param string[] $openFindingTypes
   *   Types of currently open findings.
   * @param bool[] $recentUptimeResults
   *   Recent checks, where TRUE means the agent responded successfully.
   */
  public function calculate(
    array $openFindingTypes,
    array $recentUptimeResults,
  ): int {
    $counts = array_count_values($openFindingTypes);
    $penalty = 0;
    foreach (self::FINDING_PENALTIES as $type => $rule) {
      $penalty += min(($counts[$type] ?? 0) * $rule['weight'], $rule['cap']);
    }

    if ($recentUptimeResults !== []) {
      $failures = count(array_filter(
        $recentUptimeResults,
        static fn (bool $success): bool => !$success,
      ));
      $penalty += (int) round(
        ($failures / count($recentUptimeResults)) * self::UPTIME_PENALTY_CAP,
      );
    }

    return max(0, 100 - $penalty);
  }

}
