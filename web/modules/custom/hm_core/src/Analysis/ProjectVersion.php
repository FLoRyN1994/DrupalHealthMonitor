<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

/**
 * Compares stable Drupal project versions without losing legacy prefixes.
 */
final class ProjectVersion {

  /**
   * Returns whether a version represents a stable, numeric release.
   *
   * @param string $version
   *   Project version to inspect.
   */
  public static function isStable(string $version): bool {
    return preg_match('/^(?:\d+\.x-)?\d+(?:\.\d+)*$/', $version) === 1;
  }

  /**
   * Compares two stable Drupal project versions.
   *
   * @param string $left
   *   Left project version.
   * @param string $right
   *   Right project version.
   */
  public static function compare(string $left, string $right): int {
    return version_compare(self::comparable($left), self::comparable($right));
  }

  /**
   * Returns whether a release belongs to the installed release branch.
   *
   * @param string $version
   *   Installed project version.
   * @param string[] $supportedBranches
   *   Branch prefixes supplied by drupal.org.
   */
  public static function isSupportedBranch(
    string $version,
    array $supportedBranches,
  ): bool {
    if ($supportedBranches === []) {
      return TRUE;
    }

    foreach ($supportedBranches as $branch) {
      if ($branch !== '' && str_starts_with($version, $branch)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns whether two versions belong to the same major release line.
   *
   * @param string $left
   *   Left project version.
   * @param string $right
   *   Right project version.
   */
  public static function isSameMajor(string $left, string $right): bool {
    return self::major($left) !== NULL && self::major($left) === self::major($right);
  }

  /**
   * Removes Drupal's legacy core-major prefix before comparison.
   */
  private static function comparable(string $version): string {
    return preg_replace('/^\d+\.x-/', '', $version) ?? $version;
  }

  /**
   * Extracts the project major version.
   */
  private static function major(string $version): ?int {
    $comparable = self::comparable($version);
    if (preg_match('/^(\d+)/', $comparable, $matches) !== 1) {
      return NULL;
    }
    return (int) $matches[1];
  }

}
