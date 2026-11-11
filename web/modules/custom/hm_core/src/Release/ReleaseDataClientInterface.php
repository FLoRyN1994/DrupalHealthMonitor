<?php

declare(strict_types=1);

namespace Drupal\hm_core\Release;

/**
 * Retrieves normalized Drupal project release data.
 */
interface ReleaseDataClientInterface {

  /**
   * Returns release information for a drupal.org project machine name.
   */
  public function getProject(string $projectName): ReleaseDataResult;

}
