<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\hm_core\Controller\DashboardController;
use Drupal\hm_core\Entity\Client;
use Drupal\hm_core\Entity\Finding;
use Drupal\hm_core\Entity\Site;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests dashboard filtering and HTMX enhancement.
 */
#[Group('hm_core')]
#[RunTestsInSeparateProcesses]
final class DashboardControllerTest extends KernelTestBase {

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
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Tests query filters, component props, and HTMX form attributes.
   */
  public function testFilteredDashboardBuild(): void {
    $client = Client::create([
      'name' => 'Example client',
      'contact_email' => 'owner@example.com',
      'retainer_tier' => 'standard',
    ]);
    $client->save();

    Site::create([
      'name' => 'Acme Production',
      'base_url' => 'https://prod.example.com',
      'environment' => 'prod',
      'client' => $client->id(),
      'health_score' => 100,
    ])->save();
    $staging = Site::create([
      'name' => 'Beacon Staging',
      'base_url' => 'https://stage.example.com',
      'environment' => 'stage',
      'client' => $client->id(),
      'health_score' => 82,
    ]);
    $staging->save();
    Finding::create([
      'site' => $staging->id(),
      'project_name' => 'token',
      'type' => 'update',
      'severity' => 'medium',
      'current_version' => '1.0.0',
      'recommended_version' => '1.1.0',
    ])->save();

    $request = Request::create('/admin/health-monitor/dashboard', 'GET', [
      'environment' => 'stage',
      'health' => 'attention',
    ]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);

    $controller = DashboardController::create($this->container);
    $build = $controller->build();

    $cards = $build['results']['sites']['cards'];
    self::assertCount(1, $cards);
    self::assertSame('Beacon Staging', $cards[0]['#props']['name']);
    self::assertSame(82, $cards[0]['#props']['health_score']);
    self::assertSame(1, $cards[0]['#props']['open_findings']);
    self::assertSame('token', $build['results']['findings']['#props']['findings'][0]['project_name']);

    $formAttributes = $build['filters']['#attributes'];
    self::assertSame('#hm-dashboard-results', (string) $formAttributes['data-hx-target']);
    self::assertSame('#hm-dashboard-results', (string) $formAttributes['data-hx-select']);
    self::assertSame('true', (string) $formAttributes['data-hx-push-url']);
    self::assertContains('core/drupal.htmx', $build['filters']['#attached']['library']);
    self::assertSame('stage', $build['filters']['environment']['#default_value']);
  }

}
