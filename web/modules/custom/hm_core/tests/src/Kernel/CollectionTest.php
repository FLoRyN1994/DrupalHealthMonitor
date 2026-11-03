<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\hm_core\Entity\Client as HmClient;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Entity\Snapshot;
use Drupal\hm_core\SiteCheckRunner;
use Drupal\hm_core\SiteCheckScheduler;
use Drupal\KernelTests\KernelTestBase;
use Drupal\key\Entity\Key;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\NullLogger;

/**
 * Tests monitor-side site collection and scheduling.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class CollectionTest extends KernelTestBase {

  /**
   * Shared test secret.
   */
  private const SECRET = 'monitor-side-test-secret';

  /**
   * Request timestamp used by the test.
   */
  private const TIMESTAMP = 1_798_000_000;

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
    $this->installSchema('hm_core', ['hm_uptime_check']);
  }

  /**
   * Tests saving a signed agent response and its uptime result.
   */
  public function testSuccessfulCollection(): void {
    $site = $this->createSite();
    $payload = [
      'schema_version' => 1,
      'generated_at' => self::TIMESTAMP,
      'core_version' => '11.4.11',
      'php_version' => '8.4.27',
      'modules' => [
        'system' => ['name' => 'System', 'version' => '11.4.11'],
      ],
      'pending_updates' => 0,
    ];

    $history = [];
    $handler_stack = HandlerStack::create(new MockHandler([
      new Response(200, ['Content-Type' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR)),
    ]));
    $handler_stack->push(Middleware::history($history));
    $http_client = new Client(['handler' => $handler_stack]);

    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(self::TIMESTAMP);
    $time->method('getCurrentMicroTime')->willReturnOnConsecutiveCalls(1000.0, 1000.125);

    $runner = new SiteCheckRunner(
      $http_client,
      $this->container->get('entity_type.manager'),
      $this->container->get('database'),
      $time,
      new NullLogger(),
    );
    $runner->check((int) $site->id());

    self::assertCount(1, $history);
    $request = $history[0]['request'];
    self::assertSame('https://client.example/hm-agent/status', (string) $request->getUri());
    self::assertSame((string) self::TIMESTAMP, $request->getHeaderLine('X-HM-Timestamp'));
    self::assertSame(
      hash_hmac('sha256', self::TIMESTAMP . "\n/hm-agent/status", self::SECRET),
      $request->getHeaderLine('X-HM-Signature'),
    );

    $snapshot_ids = $this->container->get('entity_type.manager')
      ->getStorage('hm_snapshot')
      ->getQuery()
      ->accessCheck(FALSE)
      ->execute();
    self::assertCount(1, $snapshot_ids);
    $snapshot = Snapshot::load(reset($snapshot_ids));
    self::assertNotNull($snapshot);
    self::assertSame('11.4.11', $snapshot->get('core_version')->value);
    self::assertSame('8.4.27', $snapshot->get('php_version')->value);

    $uptime = $this->container->get('database')
      ->select('hm_uptime_check', 'uptime')
      ->fields('uptime')
      ->execute()
      ->fetchAssoc();
    self::assertSame('200', $uptime['http_status']);
    self::assertSame('125', $uptime['response_time_ms']);
    self::assertSame('1', $uptime['success']);
  }

  /**
   * Tests that only active sites are queued.
   */
  public function testActiveSiteScheduling(): void {
    $this->createSite();
    $this->createSite('Paused site', 'paused');

    $scheduler = $this->container->get(SiteCheckScheduler::class);
    self::assertSame(1, $scheduler->enqueueActiveSites());
    self::assertSame(
      1,
      $this->container->get('queue')->get('hm_core_site_check')->numberOfItems(),
    );
  }

  /**
   * Creates a monitored site and its dependencies.
   */
  private function createSite(
    string $name = 'Active site',
    string $status = 'active',
  ): Site {
    $client = HmClient::create(['name' => $name . ' client']);
    $client->save();

    $key_id = strtolower(str_replace(' ', '_', $name)) . '_key';
    $key = Key::create([
      'id' => $key_id,
      'label' => $name . ' key',
      'key_type' => 'authentication',
      'key_provider' => 'config',
      'key_provider_settings' => ['key_value' => self::SECRET],
    ]);
    $key->save();

    $site = Site::create([
      'name' => $name,
      'base_url' => 'https://client.example',
      'environment' => 'prod',
      'client' => $client->id(),
      'agent_key' => $key->id(),
      'status' => $status,
    ]);
    $site->save();

    return $site;
  }

}
