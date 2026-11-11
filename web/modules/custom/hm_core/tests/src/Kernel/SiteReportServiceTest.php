<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\hm_core\Entity\AiReport;
use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Report\GeneratedReport;
use Drupal\hm_core\Report\ReportTextGeneratorInterface;
use Drupal\hm_core\Report\SiteReportContext;
use Drupal\hm_core\Report\SiteReportService;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests deterministic report generation and versioned persistence.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class SiteReportServiceTest extends KernelTestBase {

  /**
   * Fake report generator used by the service under test.
   */
  private TestReportTextGenerator $generator;

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

    $this->installConfig(['filter']);
    $this->installEntitySchema('hm_client');
    $this->installEntitySchema('hm_site');
    $this->installEntitySchema('hm_finding');
    $this->installEntitySchema('hm_ai_report');

    $this->generator = new TestReportTextGenerator();
    $this->container->set(ReportTextGeneratorInterface::class, $this->generator);
    $this->container->set(SiteReportService::class, new SiteReportService(
      $this->container->get('Drupal\hm_core\Report\SiteReportContextBuilder'),
      $this->generator,
      $this->container->get('entity_type.manager'),
      $this->container->get('datetime.time'),
    ));
  }

  /**
   * Tests calculation, report fields, and immutable report history.
   */
  public function testGeneratePersistsReportVersions(): void {
    $client = Client::create([
      'name' => 'Example client',
      'contact_email' => 'owner@example.com',
      'retainer_tier' => 'standard',
    ]);
    $client->save();
    $site = Site::create([
      'name' => 'Example production',
      'base_url' => 'https://example.com',
      'environment' => 'prod',
      'client' => $client->id(),
      'health_score' => 61,
    ]);
    $site->save();
    Finding::create([
      'site' => $site->id(),
      'project_name' => 'token',
      'type' => 'security',
      'severity' => 'critical',
      'source' => 'release_data',
      'current_version' => '1.0.0',
      'recommended_version' => '1.0.1',
    ])->save();
    Finding::create([
      'site' => $site->id(),
      'project_name' => 'redirect',
      'type' => 'update',
      'severity' => 'medium',
      'source' => 'release_data',
      'current_version' => '2.0.0',
      'recommended_version' => '2.1.0',
    ])->save();
    Finding::create([
      'site' => $site->id(),
      'project_name' => 'resolved',
      'type' => 'unsupported',
      'severity' => 'high',
      'source' => 'release_data',
      'resolved' => TRUE,
    ])->save();

    $service = $this->container->get(SiteReportService::class);
    $firstReport = $service->generate($site);
    $secondReport = $service->generate($site);

    self::assertNotSame($firstReport->id(), $secondReport->id());
    self::assertSame(3.75, $firstReport->get('effort_estimate')->value);
    self::assertSame('Client summary', $firstReport->get('summary_text')->value);
    self::assertSame('test:deterministic', $firstReport->get('model_used')->value);
    self::assertSame($site->id(), $firstReport->get('site')->target_id);
    self::assertCount(2, AiReport::loadMultiple());

    self::assertCount(2, $this->generator->contexts);
    self::assertSame(61, $this->generator->contexts[0]->healthScore);
    self::assertSame(3.75, $this->generator->contexts[0]->effortHours);
    self::assertCount(2, $this->generator->contexts[0]->findings);
  }

}

/**
 * Captures deterministic contexts and returns fixed generated prose.
 */
final class TestReportTextGenerator implements ReportTextGeneratorInterface {

  /**
   * Contexts received by the fake generator.
   *
   * @var \Drupal\hm_core\Report\SiteReportContext[]
   */
  public array $contexts = [];

  /**
   * {@inheritdoc}
   */
  public function generate(SiteReportContext $context): GeneratedReport {
    $this->contexts[] = $context;
    return new GeneratedReport(
      'Client summary',
      'Release digest',
      'Drupal 12 advice',
      'test:deterministic',
    );
  }

}
