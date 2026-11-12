<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Mcp\FleetToolService;
use Drupal\hm_core\Mcp\McpClientMessageStorageCompatibility;
use Drupal\hm_core\Report\GeneratedReport;
use Drupal\hm_core\Report\ReportTextGeneratorInterface;
use Drupal\hm_core\Report\SiteReportContext;
use Drupal\hm_core\Report\SiteReportService;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests Health Monitor fleet operations exposed through MCP.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class McpFleetToolsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file',
    'filter',
    'key',
    'options',
    'serialization',
    'system',
    'text',
    'user',
    'views',
    'jsonrpc',
    'mcp',
    'ai',
    'ai_provider_anthropic',
    'hm_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['filter', 'mcp']);
    $this->installEntitySchema('hm_client');
    $this->installEntitySchema('hm_site');
    $this->installEntitySchema('hm_finding');
    $this->installEntitySchema('hm_ai_report');

    $generator = new McpReportTextGenerator();
    $this->container->set(ReportTextGeneratorInterface::class, $generator);
    $this->container->set(SiteReportService::class, new SiteReportService(
      $this->container->get('Drupal\hm_core\Report\SiteReportContextBuilder'),
      $generator,
      $this->container->get('entity_type.manager'),
      $this->container->get('datetime.time'),
    ));
    $this->container->set(FleetToolService::class, new FleetToolService(
      $this->container->get('entity_type.manager'),
      $this->container->get('Drupal\hm_core\Report\SiteReportContextBuilder'),
      $this->container->get(SiteReportService::class),
    ));
  }

  /**
   * Tests deterministic fleet data, exact tool names, and report generation.
   */
  public function testFleetTools(): void {
    $mcpMessageStorage = $this->container->get('mcp.client_message.storage');
    self::assertInstanceOf(McpClientMessageStorageCompatibility::class, $mcpMessageStorage);
    $mcpMessageStorage->cleanup();

    $client = Client::create([
      'name' => 'Example client',
      'contact_email' => 'owner@example.com',
      'retainer_tier' => 'standard',
    ]);
    $client->save();
    $acme = $this->createSite($client, 'Acme Production', 50);
    $beacon = $this->createSite($client, 'Beacon Staging', 80);
    $this->createFinding($acme, 'token', 'security', 'critical');
    $this->createFinding($acme, 'redirect', 'update', 'medium');
    $this->createFinding($beacon, 'webform', 'security', 'high');
    $this->createFinding($beacon, 'resolved', 'unsupported', 'high', TRUE);

    $fleetTools = $this->container->get(FleetToolService::class);
    self::assertCount(2, $fleetTools->listSites());
    self::assertSame(3.75, $fleetTools->listSites()[0]['estimated_effort_hours']);
    self::assertSame(2, $fleetTools->getSiteFindings((int) $acme->id())['open_finding_count']);
    self::assertSame(3.75, $fleetTools->getSiteFindings((int) $acme->id())['estimated_effort_hours']);
    self::assertSame(2, $fleetTools->getSecurityUpdates()['security_update_count']);
    self::assertSame(5.5, $fleetTools->getSecurityUpdates()['estimated_effort_hours']);

    $report = $fleetTools->generateSiteReport((int) $acme->id());
    self::assertSame((int) $acme->id(), $report['site_id']);
    self::assertSame(3.75, $report['estimated_effort_hours']);
    self::assertSame('test:mcp', $report['model_used']);

    $pluginManager = $this->container->get('plugin.manager.mcp');
    $toolNames = [];
    foreach (['list', 'get', 'generate'] as $pluginId) {
      $plugin = $pluginManager->createInstance($pluginId);
      foreach ($plugin->getTools() as $tool) {
        $toolNames[] = $pluginId . '_' . $tool->name;
      }
    }
    self::assertSame([
      'list_sites',
      'get_site_findings',
      'get_security_updates',
      'generate_site_report',
    ], $toolNames);

    $listResult = $pluginManager->createInstance('list')->executeTool('sites', []);
    self::assertSame('Acme Production', $listResult['structuredContent']['sites'][0]['name']);
    $securityResult = $pluginManager->createInstance('get')->executeTool('security_updates', []);
    self::assertSame(5.5, $securityResult['structuredContent']['estimated_effort_hours']);

    try {
      $fleetTools->getSiteFindings(999999);
      self::fail('An unknown site ID must be rejected.');
    }
    catch (\InvalidArgumentException $exception) {
      self::assertSame('Monitored site 999999 was not found.', $exception->getMessage());
    }
  }

  /**
   * Creates a test monitored site.
   */
  private function createSite(Client $client, string $name, int $healthScore): Site {
    $site = Site::create([
      'name' => $name,
      'base_url' => 'https://' . strtolower(str_replace(' ', '-', $name)) . '.example.com',
      'environment' => 'prod',
      'client' => $client->id(),
      'health_score' => $healthScore,
    ]);
    $site->save();
    return $site;
  }

  /**
   * Creates a test finding.
   */
  private function createFinding(
    Site $site,
    string $projectName,
    string $type,
    string $severity,
    bool $resolved = FALSE,
  ): void {
    Finding::create([
      'site' => $site->id(),
      'project_name' => $projectName,
      'type' => $type,
      'severity' => $severity,
      'source' => 'release_data',
      'current_version' => '1.0.0',
      'recommended_version' => '1.1.0',
      'resolved' => $resolved,
    ])->save();
  }

}

/**
 * Returns stable report prose without calling an external provider.
 */
final class McpReportTextGenerator implements ReportTextGeneratorInterface {

  /**
   * {@inheritdoc}
   */
  public function generate(SiteReportContext $context): GeneratedReport {
    return new GeneratedReport(
      'Client summary',
      'Release digest',
      'Drupal 12 advice',
      'test:mcp',
    );
  }

}
