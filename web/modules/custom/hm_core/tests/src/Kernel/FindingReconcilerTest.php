<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\hm_core\Analysis\FindingCandidate;
use Drupal\hm_core\Analysis\FindingReconciler;
use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests persistence and lifecycle of analyzed findings.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class FindingReconcilerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'key',
    'options',
    'system',
    'user',
    'views',
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
  }

  /**
   * Tests create, update, resolution, and removed-project reconciliation.
   */
  public function testReconciliationLifecycle(): void {
    $client = Client::create(['name' => 'Test client']);
    $client->save();
    $site = Site::create([
      'name' => 'Test site',
      'base_url' => 'https://example.com',
      'environment' => 'prod',
      'client' => $client->id(),
    ]);
    $site->save();
    $site_id = (int) $site->id();
    $reconciler = new FindingReconciler(
      $this->container->get('entity_type.manager'),
    );

    $reconciler->reconcile($site_id, 'token', [
      'update' => new FindingCandidate('update', 'medium', '1.0.0', '1.1.0'),
    ]);
    $finding = $this->onlyFinding();
    $finding_id = $finding->id();
    self::assertFalse((bool) $finding->get('resolved')->value);
    self::assertSame('1.1.0', $finding->get('recommended_version')->value);

    $reconciler->reconcile($site_id, 'token', [
      'update' => new FindingCandidate('update', 'medium', '1.0.0', '1.2.0'),
    ]);
    $finding = $this->onlyFinding();
    self::assertSame($finding_id, $finding->id());
    self::assertSame('1.2.0', $finding->get('recommended_version')->value);

    $reconciler->reconcile($site_id, 'token', []);
    self::assertTrue((bool) $this->onlyFinding()->get('resolved')->value);

    $reconciler->reconcile($site_id, 'token', [
      'update' => new FindingCandidate('update', 'medium', '1.0.0', '1.3.0'),
    ]);
    self::assertFalse((bool) $this->onlyFinding()->get('resolved')->value);

    $reconciler->resolveRemovedProjects($site_id, ['drupal']);
    self::assertTrue((bool) $this->onlyFinding()->get('resolved')->value);
  }

  /**
   * Loads the only finding in storage.
   */
  private function onlyFinding(): Finding {
    $storage = $this->container->get('entity_type.manager')->getStorage('hm_finding');
    $entities = array_values($storage->loadMultiple());
    self::assertCount(1, $entities);
    self::assertInstanceOf(Finding::class, $entities[0]);
    return $entities[0];
  }

}
