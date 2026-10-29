<?php

declare(strict_types=1);

namespace Drupal\hm_core\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides hooks for the Health Monitor Core module.
 */
final class HmCoreHooks {

  use StringTranslationTrait;

  /**
   * Provides help text for the module help page.
   */
  #[Hook('help')]
  public function help(string $routeName, RouteMatchInterface $routeMatch): ?string {
    if ($routeName !== 'help.page.hm_core') {
      return NULL;
    }

    return '<p>' . $this->t('Provides fleet monitoring, analysis, and reporting for Drupal sites.') . '</p>';
  }

}
