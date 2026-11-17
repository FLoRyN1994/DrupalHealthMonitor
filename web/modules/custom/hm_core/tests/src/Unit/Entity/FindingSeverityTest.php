<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Unit\Entity;

use Drupal\hm_core\Entity\Finding;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the severity ranking used to order findings.
 */
#[CoversClass(Finding::class)]
#[Group('hm_core')]
final class FindingSeverityTest extends UnitTestCase {

  /**
   * Tests that severities rank by urgency and unknown values rank last.
   */
  public function testSeverityRank(): void {
    $ranks = array_map(Finding::severityRank(...), ['critical', 'high', 'medium', 'low', 'info', 'bogus']);

    self::assertSame($ranks, array_values(array_unique($ranks)));
    $sorted = $ranks;
    sort($sorted);
    self::assertSame($sorted, $ranks);
  }

}
