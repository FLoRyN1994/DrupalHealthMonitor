<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Unit\Hook;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\hm_core\Hook\HmCoreHooks;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the module hooks.
 */
#[CoversClass(HmCoreHooks::class)]
#[Group('hm_core')]
final class HmCoreHooksTest extends UnitTestCase {

  /**
   * Tests that help text is limited to the module help page.
   */
  public function testHelp(): void {
    $hooks = new HmCoreHooks();
    $hooks->setStringTranslation($this->getStringTranslationStub());
    $routeMatch = $this->createMock(RouteMatchInterface::class);

    self::assertNull($hooks->help('system.admin', $routeMatch));
    self::assertSame(
      '<p>Provides fleet monitoring, analysis, and reporting for Drupal sites.</p>',
      $hooks->help('help.page.hm_core', $routeMatch),
    );
  }

}
