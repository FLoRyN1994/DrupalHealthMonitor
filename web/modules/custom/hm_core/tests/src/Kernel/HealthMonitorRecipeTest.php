<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Kernel;

use Drupal\block\Entity\Block;
use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\hm_core\Entity\Site;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the complete Health Monitor recipe on a fresh kernel site.
 */
#[Group('hm_core')]
#[Group('Recipe')]
#[IgnoreDeprecations]
#[RunTestsInSeparateProcesses]
final class HealthMonitorRecipeTest extends KernelTestBase {

  /**
   * Error reporting level restored after contrib modules are installed.
   */
  private int $originalErrorReporting;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    // JSON-RPC 2.1 emits a PHP 8.4 signature deprecation during installation.
    $this->originalErrorReporting = error_reporting();
    error_reporting($this->originalErrorReporting & ~E_DEPRECATED);
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user']);
    $admin = User::create([
      'uid' => 1,
      'name' => 'admin',
      'status' => 1,
    ]);
    $admin->save();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    error_reporting($this->originalErrorReporting);
    parent::tearDown();
  }

  /**
   * Tests installation, demo content, configuration, and repeat application.
   */
  public function testRecipe(): void {
    $this->applyRecipe();

    foreach (['hm_core', 'mcp', 'simple_oauth'] as $module) {
      self::assertTrue($this->container->get('module_handler')->moduleExists($module));
    }
    self::assertTrue($this->container->get('theme_handler')->themeExists('hm_theme'));
    self::assertSame('hm_theme', $this->config('system.theme')->get('default'));
    self::assertSame('/health-monitor', $this->config('system.site')->get('page.front'));

    self::assertNotNull(Block::load('hm_theme_content'));
    self::assertNotNull(View::load('hm_clients'));
    self::assertNotNull(View::load('hm_sites'));
    self::assertSame(['mcp_client'], $this->config('mcp.settings')->get('plugins.list.roles'));
    self::assertSame('use mcp server', $this->config('simple_oauth.oauth2_scope.health_monitor_mcp')->get('granularity_configuration.permission'));

    self::assertSame(2, $this->entityCount('hm_client'));
    self::assertSame(2, $this->entityCount('hm_site'));
    self::assertSame(2, $this->entityCount('hm_snapshot'));
    self::assertSame(5, $this->entityCount('hm_finding'));

    $site = $this->container->get('entity.repository')->loadEntityByUuid(
      'hm_site',
      '64373c6e-001b-4bbf-9354-8b61e8496063',
    );
    self::assertInstanceOf(Site::class, $site);
    self::assertSame('Acme Production', $site->label());
    self::assertSame('paused', $site->get('status')->value);
    self::assertSame(45, (int) $site->get('health_score')->value);

    $this->applyRecipe();
    self::assertSame(2, $this->entityCount('hm_site'));
    self::assertSame(5, $this->entityCount('hm_finding'));
  }

  /**
   * Applies the repository recipe.
   */
  private function applyRecipe(): void {
    $recipe = Recipe::createFromDirectory(DRUPAL_ROOT . '/../recipes/health_monitor');
    RecipeRunner::processRecipe($recipe);
  }

  /**
   * Counts entities without access checks.
   */
  private function entityCount(string $entityTypeId): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage($entityTypeId)
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
