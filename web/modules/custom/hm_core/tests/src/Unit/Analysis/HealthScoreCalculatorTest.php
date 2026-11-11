<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Unit\Analysis;

use Drupal\hm_core\Analysis\HealthScoreCalculator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests deterministic health score calculation.
 */
#[CoversClass(HealthScoreCalculator::class)]
#[Group('hm_core')]
final class HealthScoreCalculatorTest extends TestCase {

  /**
   * Tests finding weights and independent caps.
   *
   * @param string[] $findingTypes
   *   Open finding types.
   * @param int $expectedScore
   *   Expected score.
   */
  #[DataProvider('findingCases')]
  public function testFindingPenalties(
    array $findingTypes,
    int $expectedScore,
  ): void {
    $score = (new HealthScoreCalculator())->calculate(
      $findingTypes,
      [TRUE, TRUE, TRUE],
    );
    self::assertSame($expectedScore, $score);
  }

  /**
   * Provides finding penalty cases.
   *
   * @return array<string, array{0: string[], 1: int}>
   *   Named test cases.
   */
  public static function findingCases(): array {
    return [
      'healthy' => [[], 100],
      'security' => [['security'], 70],
      'unsupported' => [['unsupported'], 85],
      'd12 blocker' => [['d12_blocker'], 90],
      'regular update' => [['update'], 95],
      'security cap' => [array_fill(0, 5, 'security'), 40],
      'update cap' => [array_fill(0, 8, 'update'), 80],
    ];
  }

  /**
   * Tests proportional uptime penalty and rounding.
   */
  public function testUptimePenalty(): void {
    $results = array_merge(array_fill(0, 9, TRUE), array_fill(0, 3, FALSE));
    self::assertSame(
      92,
      (new HealthScoreCalculator())->calculate([], $results),
    );
  }

  /**
   * Tests combined penalties never produce a negative score.
   */
  public function testLowerBoundary(): void {
    self::assertSame(
      0,
      (new HealthScoreCalculator())->calculate(
        [
          'security',
          'security',
          'unsupported',
          'unsupported',
          'd12_blocker',
          'd12_blocker',
          'update',
          'update',
          'update',
          'update',
        ],
        [FALSE],
      ),
    );
  }

  /**
   * Tests absent uptime history has no speculative penalty.
   */
  public function testNoUptimeHistory(): void {
    self::assertSame(
      95,
      (new HealthScoreCalculator())->calculate(['update'], []),
    );
  }

}
