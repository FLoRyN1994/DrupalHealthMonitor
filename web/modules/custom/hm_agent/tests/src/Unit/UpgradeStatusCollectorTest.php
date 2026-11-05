<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_agent\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\hm_agent\UpgradeStatusCollector;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests normalization of optional Upgrade Status scan data.
 */
#[CoversClass(UpgradeStatusCollector::class)]
#[Group('hm_agent')]
final class UpgradeStatusCollectorTest extends TestCase {

  /**
   * Fixed collection time.
   */
  private const NOW = 1_800_000_000;

  /**
   * Tests the explicit not-installed state.
   */
  public function testNotInstalled(): void {
    $result = $this->collector(FALSE, [])->collect();
    self::assertSame('not_installed', $result['status']);
    self::assertSame([], $result['projects']);
  }

  /**
   * Tests the explicit never-scanned state.
   */
  public function testNeverScanned(): void {
    $result = $this->collector(TRUE, [])->collect();
    self::assertSame('never_scanned', $result['status']);
  }

  /**
   * Tests bounded normalization and per-project staleness.
   */
  public function testAvailableResults(): void {
    $result = $this->collector(TRUE, [
      'token' => $this->rawResult(self::NOW - 60, 3, 2, 5, 'rector'),
      'old_project' => $this->rawResult(self::NOW - 2_592_001, 1, 0, 1, 'manual'),
    ])->collect();

    self::assertSame('available', $result['status']);
    self::assertCount(2, $result['projects']);
    self::assertSame([
      'project' => 'token',
      'scanned_at' => self::NOW - 60,
      'stale' => FALSE,
      'errors' => 3,
      'warnings' => 2,
      'total_issues' => 5,
      'next_action' => 'rector',
    ], $result['projects'][0]);
    self::assertTrue($result['projects'][1]['stale']);
  }

  /**
   * Tests all-stale and malformed-result states remain distinct.
   */
  public function testStaleAndFailedResults(): void {
    $stale = $this->collector(TRUE, [
      'token' => $this->rawResult(self::NOW - 2_592_001, 1, 0, 1, 'manual'),
    ])->collect();
    self::assertSame('stale', $stale['status']);

    $failed = $this->collector(TRUE, [
      'token' => [
        'date' => self::NOW,
        'data' => [
          'totals' => [
            'file_errors' => 1,
            'upgrade_status_split' => ['warning' => 1],
          ],
        ],
      ],
    ])->collect();
    self::assertSame('failed', $failed['status']);
    self::assertSame([], $failed['projects']);
  }

  /**
   * Creates a collector with mocked Drupal storage.
   *
   * @param bool $installed
   *   Whether Upgrade Status is enabled.
   * @param array<string, mixed> $storedResults
   *   Raw key/value results.
   */
  private function collector(
    bool $installed,
    array $storedResults,
  ): UpgradeStatusCollector {
    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('moduleExists')->with('upgrade_status')->willReturn($installed);
    $store = $this->createMock(KeyValueStoreInterface::class);
    $store->method('getAll')->willReturn($storedResults);
    $factory = $this->createMock(KeyValueFactoryInterface::class);
    $factory->method('get')->with('upgrade_status_scan_results')->willReturn($store);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(self::NOW);
    return new UpgradeStatusCollector($module_handler, $factory, $time);
  }

  /**
   * Creates the relevant subset of a raw saved scan result.
   *
   * @return array<string, mixed>
   *   Raw Upgrade Status result.
   */
  private function rawResult(
    int $date,
    int $errors,
    int $warnings,
    int $totalIssues,
    string $nextAction,
  ): array {
    return [
      'date' => $date,
      'data' => [
        'totals' => [
          'file_errors' => $totalIssues,
          'upgrade_status_next' => $nextAction,
          'upgrade_status_split' => [
            'error' => $errors,
            'warning' => $warnings,
          ],
        ],
      ],
    ];
  }

}
