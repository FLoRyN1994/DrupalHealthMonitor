<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Analysis\FindingCandidate;
use Drupal\hm_core\Analysis\FindingReconciler;
use Drupal\hm_core\DataRetention;
use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Entity\Snapshot;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests relationship cleanup, retention, and finding ordering.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class DataIntegrityTest extends KernelTestBase {

  use UserCreationTrait;

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
    $this->installEntitySchema('user');
    $this->installEntitySchema('hm_client');
    $this->installEntitySchema('hm_site');
    $this->installEntitySchema('hm_snapshot');
    $this->installEntitySchema('hm_finding');
    $this->installEntitySchema('hm_ai_report');
    $this->installSchema('hm_core', ['hm_uptime_check']);
  }

  /**
   * Tests that deleting a site deletes everything that belongs to it.
   */
  public function testSiteDeleteRemovesDependentData(): void {
    $client = $this->createClient();
    $deleted_site = $this->createSite($client, 'Deleted');
    $kept_site = $this->createSite($client, 'Kept');
    foreach ([$deleted_site, $kept_site] as $site) {
      $this->createSnapshot($site, 1_000);
      $this->createFinding($site, 'token', 'low');
      $this->createUptimeRow($site, 1_000);
      $this->entityTypeManager()->getStorage('hm_ai_report')->create([
        'title' => 'Report',
        'site' => $site->id(),
        'generated_at' => 1_000,
        'summary_text' => 'Summary',
        'effort_estimate' => '1.0',
        'model_used' => 'test',
      ])->save();
    }

    $deleted_site->delete();

    foreach (['hm_snapshot', 'hm_finding', 'hm_ai_report'] as $entity_type_id) {
      $remaining = $this->entityTypeManager()->getStorage($entity_type_id)->loadMultiple();
      self::assertCount(1, $remaining, $entity_type_id);
      $entity = reset($remaining);
      self::assertInstanceOf(ContentEntityInterface::class, $entity);
      self::assertSame($kept_site->id(), $entity->get('site')->target_id, $entity_type_id);
    }
    self::assertSame([(string) $kept_site->id()], $this->uptimeSiteIds());
  }

  /**
   * Tests that a client cannot be deleted while it owns sites.
   */
  public function testClientDeleteIsBlockedWhileSitesExist(): void {
    // User 1 bypasses access checks, so create it first.
    $this->createUser();
    $admin = $this->createUser(['administer health monitor']);
    $client = $this->createClient();
    $site = $this->createSite($client, 'Owned');

    self::assertFalse($client->access('delete', $admin));
    self::assertTrue($client->access('update', $admin));

    $site->delete();
    $this->entityTypeManager()->getAccessControlHandler('hm_client')->resetCache();
    self::assertTrue($client->access('delete', $admin));
  }

  /**
   * Tests that old history is purged while each site keeps its latest data.
   */
  public function testRetentionPurgesExpiredHistory(): void {
    $now = (int) $this->container->get('datetime.time')->getRequestTime();
    $old = $now - DataRetention::UPTIME_RETENTION - 10;
    $client = $this->createClient();
    $busy_site = $this->createSite($client, 'Busy');
    $paused_site = $this->createSite($client, 'Paused');

    $expired_snapshot = $this->createSnapshot($busy_site, $old);
    $recent_snapshot = $this->createSnapshot($busy_site, $now);
    // The only snapshot of a paused site is old but must be kept.
    $paused_snapshot = $this->createSnapshot($paused_site, $old);
    $this->createUptimeRow($busy_site, $old);
    $this->createUptimeRow($busy_site, $now);

    $deleted = $this->container->get(DataRetention::class)->purge();

    self::assertSame(['uptime' => 1, 'snapshots' => 1], $deleted);
    self::assertNull(Snapshot::load($expired_snapshot->id()));
    self::assertNotNull(Snapshot::load($recent_snapshot->id()));
    self::assertNotNull(Snapshot::load($paused_snapshot->id()));
    self::assertSame([(string) $busy_site->id()], $this->uptimeSiteIds());
  }

  /**
   * Tests that reconciling unchanged evidence does not rewrite findings.
   */
  public function testUnchangedReconciliationDoesNotSave(): void {
    $site = $this->createSite($this->createClient(), 'Stable');
    $reconciler = new FindingReconciler($this->entityTypeManager());
    $candidate = ['update' => new FindingCandidate('update', 'medium', '1.0.0', '1.1.0')];

    $reconciler->reconcile((int) $site->id(), 'token', $candidate);
    $this->container->get('database')->update('hm_finding')
      ->fields(['changed' => 1])
      ->execute();

    $reconciler->reconcile((int) $site->id(), 'token', $candidate);
    self::assertSame('1', $this->reloadOnlyFinding()->get('changed')->value);

    $reconciler->reconcile((int) $site->id(), 'token', [
      'update' => new FindingCandidate('update', 'medium', '1.0.0', '1.2.0'),
    ]);
    self::assertNotSame('1', $this->reloadOnlyFinding()->get('changed')->value);
  }

  /**
   * Tests that findings sort by real urgency rather than alphabetically.
   */
  public function testFindingsSortByUrgency(): void {
    $site = $this->createSite($this->createClient(), 'Sorted');
    $findings = [
      $this->createFinding($site, 'beta', 'low'),
      $this->createFinding($site, 'zeta', 'critical'),
      $this->createFinding($site, 'alpha', 'medium'),
      $this->createFinding($site, 'alpha', 'high'),
      $this->createFinding($site, 'gamma', 'info'),
    ];

    $sorted = array_map(
      static fn (Finding $finding): string => $finding->get('severity')->value . ':' . $finding->get('project_name')->value,
      Finding::sortByUrgency($findings),
    );

    self::assertSame(
      ['critical:zeta', 'high:alpha', 'medium:alpha', 'low:beta', 'info:gamma'],
      $sorted,
    );
  }

  /**
   * Returns the entity type manager.
   */
  private function entityTypeManager(): EntityTypeManagerInterface {
    return $this->container->get('entity_type.manager');
  }

  /**
   * Creates a client.
   */
  private function createClient(): Client {
    $client = Client::create(['name' => 'Agency client']);
    $client->save();
    return $client;
  }

  /**
   * Creates a site for a client.
   */
  private function createSite(Client $client, string $name): Site {
    $site = Site::create([
      'name' => $name,
      'base_url' => 'https://' . strtolower($name) . '.example',
      'environment' => 'prod',
      'client' => $client->id(),
    ]);
    $site->save();
    return $site;
  }

  /**
   * Creates a snapshot checked at the given time.
   */
  private function createSnapshot(Site $site, int $checkedAt): Snapshot {
    $snapshot = Snapshot::create([
      'site' => $site->id(),
      'checked_at' => $checkedAt,
      'core_version' => '11.4.12',
      'php_version' => '8.4.0',
      'modules' => '{}',
      'pending_updates' => 0,
    ]);
    $snapshot->save();
    return $snapshot;
  }

  /**
   * Creates an unresolved update finding.
   */
  private function createFinding(Site $site, string $project, string $severity): Finding {
    $finding = Finding::create([
      'site' => $site->id(),
      'project_name' => $project,
      'type' => 'update',
      'severity' => $severity,
      'source' => 'release_data',
    ]);
    $finding->save();
    return $finding;
  }

  /**
   * Inserts an uptime row.
   */
  private function createUptimeRow(Site $site, int $checkedAt): void {
    $this->container->get('database')->insert('hm_uptime_check')
      ->fields([
        'site_id' => $site->id(),
        'checked_at' => $checkedAt,
        'http_status' => 200,
        'response_time_ms' => 100,
        'success' => 1,
      ])
      ->execute();
  }

  /**
   * Returns the site IDs of all uptime rows.
   *
   * @return list<string>
   *   Site IDs.
   */
  private function uptimeSiteIds(): array {
    return array_values($this->container->get('database')
      ->select('hm_uptime_check', 'uptime')
      ->fields('uptime', ['site_id'])
      ->execute()
      ->fetchCol());
  }

  /**
   * Reloads the only finding from storage, bypassing the static cache.
   */
  private function reloadOnlyFinding(): Finding {
    $storage = $this->entityTypeManager()->getStorage('hm_finding');
    $storage->resetCache();
    $findings = array_values($storage->loadMultiple());
    self::assertCount(1, $findings);
    self::assertInstanceOf(Finding::class, $findings[0]);
    return $findings[0];
  }

}
