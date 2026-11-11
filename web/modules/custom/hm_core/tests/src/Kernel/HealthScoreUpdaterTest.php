<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\hm_core\Analysis\HealthScoreCalculator;
use Drupal\hm_core\Analysis\HealthScoreUpdater;
use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests score calculation from persisted findings and uptime checks.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class HealthScoreUpdaterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'filter',
    'key',
    'options',
    'system',
    'text',
    'user',
    'views',
    'ai',
    'ai_provider_anthropic',
    'hm_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('hm_client');
    $this->installEntitySchema('hm_site');
    $this->installEntitySchema('hm_finding');
    $this->installSchema('hm_core', ['hm_uptime_check']);
  }

  /**
   * Tests open findings, resolved findings, and the 12-check uptime window.
   */
  public function testUpdate(): void {
    $client = Client::create(['name' => 'Score client']);
    $client->save();
    $site = Site::create([
      'name' => 'Score site',
      'base_url' => 'https://score.example',
      'environment' => 'prod',
      'client' => $client->id(),
    ]);
    $site->save();
    $site_id = (int) $site->id();

    Finding::create([
      'site' => $site_id,
      'project_name' => 'example',
      'type' => 'security',
      'severity' => 'critical',
      'current_version' => '1.0.0',
    ])->save();
    Finding::create([
      'site' => $site_id,
      'project_name' => 'old_project',
      'type' => 'unsupported',
      'severity' => 'high',
      'current_version' => '1.0.0',
      'resolved' => TRUE,
    ])->save();

    $results = array_merge([FALSE], array_fill(0, 9, TRUE), array_fill(0, 3, FALSE));
    foreach ($results as $index => $success) {
      $this->container->get('database')->insert('hm_uptime_check')
        ->fields([
          'site_id' => $site_id,
          'checked_at' => $index + 1,
          'http_status' => $success ? 200 : 0,
          'response_time_ms' => 10,
          'success' => (int) $success,
          'error' => $success ? NULL : 'Unavailable',
        ])
        ->execute();
    }

    $updater = new HealthScoreUpdater(
      $this->container->get('entity_type.manager'),
      $this->container->get('database'),
      new HealthScoreCalculator(),
    );
    $updater->update($site_id);

    self::assertSame(62, (int) Site::load($site_id)?->get('health_score')->value);
  }

}
