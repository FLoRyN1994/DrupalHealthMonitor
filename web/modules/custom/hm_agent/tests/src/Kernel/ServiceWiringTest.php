<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_agent\Kernel;

use Drupal\hm_agent\StatusCollector;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the agent services compile in Drupal's container.
 */
#[Group('hm_agent')]
#[RunTestsInSeparateProcesses]
final class ServiceWiringTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'hm_agent',
  ];

  /**
   * Tests that the status collector and its dependencies are wired.
   */
  public function testStatusCollectorService(): void {
    self::assertTrue($this->container->has(StatusCollector::class));
  }

}
