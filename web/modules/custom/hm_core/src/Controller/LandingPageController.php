<?php

declare(strict_types=1);

namespace Drupal\hm_core\Controller;

/**
 * Builds the public product landing page shell.
 */
final class LandingPageController {

  /**
   * Returns content rendered by the theme's front-page presentation.
   *
   * @return array<string, mixed>
   *   A render array.
   */
  public function build(): array {
    return [
      '#markup' => '',
      '#cache' => [
        'contexts' => ['user.roles:authenticated'],
      ],
    ];
  }

}
