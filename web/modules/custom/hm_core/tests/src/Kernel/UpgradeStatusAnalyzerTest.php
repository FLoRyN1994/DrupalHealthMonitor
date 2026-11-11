<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\hm_core\Analysis\FindingReconciler;
use Drupal\hm_core\Analysis\UpgradeStatusAnalyzer;
use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Entity\Snapshot;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests conversion of fresh Upgrade Status evidence into findings.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class UpgradeStatusAnalyzerTest extends KernelTestBase {

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
    $this->installEntitySchema('hm_snapshot');
    $this->installEntitySchema('hm_finding');
  }

  /**
   * Tests creation and resolution from fresh scans.
   */
  public function testFindingLifecycle(): void {
    $client = Client::create(['name' => 'Upgrade client']);
    $client->save();
    $site = Site::create([
      'name' => 'Upgrade site',
      'base_url' => 'https://upgrade.example',
      'environment' => 'prod',
      'client' => $client->id(),
    ]);
    $site->save();
    $site_id = (int) $site->id();
    $reconciler = new FindingReconciler(
      $this->container->get('entity_type.manager'),
    );
    $analyzer = new UpgradeStatusAnalyzer(
      $this->container->get('entity_type.manager'),
      $reconciler,
    );

    $analyzer->analyze($this->snapshot($site_id, 2));
    $finding = $this->upgradeStatusFinding();
    self::assertFalse((bool) $finding->get('resolved')->value);
    self::assertSame('8.x-1.22', $finding->get('current_version')->value);

    $analyzer->analyze($this->snapshot($site_id, 0));
    self::assertTrue((bool) $this->upgradeStatusFinding()->get('resolved')->value);
  }

  /**
   * Tests stale and non-D12 scans cannot clear a current finding.
   */
  public function testIncompleteEvidenceIsIgnored(): void {
    $client = Client::create(['name' => 'Conservative client']);
    $client->save();
    $site = Site::create([
      'name' => 'Conservative site',
      'base_url' => 'https://conservative.example',
      'environment' => 'prod',
      'client' => $client->id(),
    ]);
    $site->save();
    $site_id = (int) $site->id();
    Finding::create([
      'site' => $site_id,
      'project_name' => 'token',
      'type' => 'd12_blocker',
      'severity' => 'high',
      'source' => 'upgrade_status',
    ])->save();
    $reconciler = new FindingReconciler(
      $this->container->get('entity_type.manager'),
    );
    $analyzer = new UpgradeStatusAnalyzer(
      $this->container->get('entity_type.manager'),
      $reconciler,
    );

    $analyzer->analyze($this->snapshot($site_id, 0, TRUE));
    self::assertFalse((bool) $this->upgradeStatusFinding()->get('resolved')->value);
    $analyzer->analyze($this->snapshot($site_id, 0, FALSE, 11));
    self::assertFalse((bool) $this->upgradeStatusFinding()->get('resolved')->value);
  }

  /**
   * Creates a Snapshot carrying one normalized project scan.
   */
  private function snapshot(
    int $siteId,
    int $errors,
    bool $stale = FALSE,
    int $targetMajor = 12,
  ): Snapshot {
    $upgrade_status = [
      'schema_version' => 1,
      'status' => $stale ? 'stale' : 'available',
      'target_major' => $targetMajor,
      'collected_at' => 1_800_000_000,
      'projects' => [
        [
          'project' => 'token',
          'scanned_at' => 1_800_000_000,
          'stale' => $stale,
          'errors' => $errors,
          'warnings' => 0,
          'total_issues' => $errors,
          'next_action' => $errors > 0 ? 'manual' : 'relax',
        ],
      ],
    ];
    $modules = [
      'token' => [
        'name' => 'Token',
        'version' => '8.x-1.22',
        'project' => 'token',
        'origin' => 'contrib',
      ],
    ];
    $snapshot = Snapshot::create([
      'site' => $siteId,
      'checked_at' => 1_800_000_000,
      'core_version' => '11.4.0',
      'php_version' => '8.4.0',
      'modules' => json_encode($modules, JSON_THROW_ON_ERROR),
      'pending_updates' => 0,
      'raw_payload' => json_encode([
        'upgrade_status' => $upgrade_status,
      ], JSON_THROW_ON_ERROR),
    ]);
    $snapshot->save();
    return $snapshot;
  }

  /**
   * Loads the Upgrade Status finding.
   */
  private function upgradeStatusFinding(): Finding {
    $storage = $this->container->get('entity_type.manager')->getStorage('hm_finding');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('source', 'upgrade_status')
      ->execute();
    self::assertCount(1, $ids);
    $finding = $storage->load(reset($ids));
    self::assertInstanceOf(Finding::class, $finding);
    return $finding;
  }

}
