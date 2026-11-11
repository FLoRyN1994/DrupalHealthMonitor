<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\KernelTests\Components\ComponentKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the Health Monitor dashboard components.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class DashboardComponentTest extends ComponentKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $themes = ['hm_theme'];

  /**
   * Tests component schemas, nesting, output, and asset discovery.
   */
  public function testDashboardComponentsRender(): void {
    $build = [
      '#type' => 'container',
      'site' => [
        '#type' => 'component',
        '#component' => 'hm_theme:site-card',
        '#props' => [
          'name' => 'Example Production',
          'base_url' => 'https://example.com',
          'environment' => 'Production',
          'status' => 'Active',
          'health_score' => 84,
          'open_findings' => 1,
          'detail_url' => '/admin/health-monitor/sites/1',
        ],
      ],
      'findings' => [
        '#type' => 'component',
        '#component' => 'hm_theme:findings-table',
        '#props' => [
          'caption' => 'Open findings across the fleet',
          'findings' => [
            [
              'site_name' => 'Example Production',
              'site_url' => '/admin/health-monitor/sites/1',
              'project_name' => 'Drupal core',
              'type' => 'Security update',
              'severity' => 'critical',
              'current_version' => '11.4.10',
              'recommended_version' => '11.4.11',
            ],
          ],
        ],
      ],
    ];

    $metadata = new BubbleableMetadata();
    $crawler = $this->renderComponentRenderArray($build, $metadata);

    self::assertCount(1, $crawler->filter('.site-card'));
    self::assertSame('Example Production', $crawler->filter('.site-card__title')->text());
    self::assertSame('84/100', str_replace(' ', '', $crawler->filter('.health-badge')->text()));
    self::assertSame('1 open finding', trim($crawler->filter('.site-card__finding-count')->text()));
    self::assertSame('Drupal core', $crawler->filter('.findings-table tbody td')->eq(0)->text());
    self::assertSame('critical', $crawler->filter('.findings-table__severity--critical')->text());

    $libraries = $metadata->getAttachments()['library'] ?? [];
    self::assertContains('core/components.hm_theme--health-badge', $libraries);
    self::assertContains('core/components.hm_theme--site-card', $libraries);
    self::assertContains('core/components.hm_theme--findings-table', $libraries);
  }

}
