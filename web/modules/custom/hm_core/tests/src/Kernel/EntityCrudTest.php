<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Entity\Snapshot;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests CRUD operations for Health Monitor entities.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class EntityCrudTest extends KernelTestBase {

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
    $this->installEntitySchema('hm_snapshot');
    $this->installEntitySchema('hm_finding');
  }

  /**
   * Tests creating, reading, updating, and deleting all four entity types.
   */
  public function testEntityCrud(): void {
    $client = Client::create([
      'name' => 'Example Agency Client',
      'contact_email' => 'owner@example.com',
      'retainer_tier' => 'standard',
    ]);
    $client->save();
    self::assertNotNull($client->id());
    self::assertSame('Example Agency Client', Client::load($client->id())?->label());

    $site = Site::create([
      'name' => 'Example production',
      'base_url' => 'https://example.com',
      'environment' => 'prod',
      'client' => $client->id(),
    ]);
    $site->save();
    self::assertSame('active', $site->get('status')->value);
    self::assertSame(100, (int) $site->get('health_score')->value);

    $snapshot = Snapshot::create([
      'site' => $site->id(),
      'checked_at' => 1_798_000_000,
      'core_version' => '11.4.0',
      'php_version' => '8.4.0',
      'modules' => '{"node":"11.4.0"}',
      'pending_updates' => 0,
      'raw_payload' => '{"status":"ok"}',
    ]);
    $snapshot->save();
    self::assertNotNull(Snapshot::load($snapshot->id()));

    $finding = Finding::create([
      'site' => $site->id(),
      'project_name' => 'drupal',
      'type' => 'security',
      'severity' => 'critical',
      'current_version' => '11.4.0',
      'recommended_version' => '11.4.1',
    ]);
    $finding->save();
    self::assertFalse((bool) $finding->get('resolved')->value);

    $site->set('health_score', 42)->save();
    $finding->set('resolved', TRUE)->save();
    self::assertSame(42, (int) Site::load($site->id())?->get('health_score')->value);
    self::assertTrue((bool) Finding::load($finding->id())?->get('resolved')->value);

    $finding->delete();
    $snapshot->delete();
    $site->delete();
    $client->delete();
    self::assertNull(Client::load($client->id()));
    self::assertNull(Site::load($site->id()));
    self::assertNull(Snapshot::load($snapshot->id()));
    self::assertNull(Finding::load($finding->id()));
  }

}
