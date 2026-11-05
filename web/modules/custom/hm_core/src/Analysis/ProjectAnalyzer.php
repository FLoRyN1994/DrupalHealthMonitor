<?php

declare(strict_types=1);

namespace Drupal\hm_core\Analysis;

use Composer\Semver\Semver;
use Drupal\hm_core\Release\ProjectRelease;
use Drupal\hm_core\Release\ProjectReleaseData;

/**
 * Deterministically analyzes one installed drupal.org project.
 */
final class ProjectAnalyzer {

  /**
   * Creates the findings supported by the supplied release evidence.
   *
   * @return array<string, \Drupal\hm_core\Analysis\FindingCandidate>
   *   Candidates keyed by finding type.
   */
  public function analyze(
    ProjectReleaseData $project,
    string $installedVersion,
    string $coreVersion,
    bool $checkDrupal12 = TRUE,
  ): array {
    $candidates = [];
    $branch_releases = $this->branchReleases(
      $project,
      $installedVersion,
      $coreVersion,
    );
    $current_release = $this->findInstalledRelease($project, $installedVersion);
    $latest_release = $this->latest($branch_releases);

    $security_release = $this->latest(array_filter(
      $branch_releases,
      static fn (ProjectRelease $release): bool => $release->isSecurityUpdate()
        && ProjectVersion::compare($release->version, $installedVersion) > 0,
    ));
    $is_insecure = $current_release?->isInsecure() ?? FALSE;
    if ($is_insecure || $security_release !== NULL) {
      $recommended = $this->latest(array_filter(
        $branch_releases,
        static fn (ProjectRelease $release): bool => $release->securityCovered
          && ProjectVersion::compare($release->version, $installedVersion) > 0,
      ));
      $recommended_version = $recommended !== NULL
        ? $recommended->version
        : ($security_release !== NULL ? $security_release->version : '');
      $candidates['security'] = new FindingCandidate(
        'security',
        'critical',
        $installedVersion,
        $recommended_version,
      );
    }

    $unsupported = $project->projectStatus !== 'published'
      || $project->releases === []
      || !ProjectVersion::isSupportedBranch($installedVersion, $project->supportedBranches)
      || ($current_release !== NULL && !$current_release->securityCovered);
    if ($unsupported) {
      $recommended = $this->latest(array_filter(
        $project->releases,
        fn (ProjectRelease $release): bool => $this->isUsableRelease(
          $release,
          $coreVersion,
          $project->machineName === 'drupal',
        )
          && $release->securityCovered,
      ));
      $candidates['unsupported'] = new FindingCandidate(
        'unsupported',
        'high',
        $installedVersion,
        $recommended !== NULL ? $recommended->version : '',
      );
    }

    if (!isset($candidates['security'])
      && $latest_release !== NULL
      && ProjectVersion::compare($latest_release->version, $installedVersion) > 0
    ) {
      $candidates['update'] = new FindingCandidate(
        'update',
        'medium',
        $installedVersion,
        $latest_release->version,
      );
    }

    if ($checkDrupal12) {
      $d12_release = $this->latest(array_filter(
        $project->releases,
        fn (ProjectRelease $release): bool => $this->isDrupal12Release($release),
      ));
      $has_unknown_compatibility = array_any(
        $project->releases,
        static fn (ProjectRelease $release): bool => $release->status === 'published'
          && ProjectVersion::isStable($release->version)
          && $release->coreCompatibility === '',
      );
      if ($d12_release === NULL && !$has_unknown_compatibility) {
        $candidates['d12_blocker'] = new FindingCandidate(
          'd12_blocker',
          'high',
          $installedVersion,
          '',
        );
      }
    }

    return $candidates;
  }

  /**
   * Returns stable releases in the installed major line usable on this core.
   *
   * @return \Drupal\hm_core\Release\ProjectRelease[]
   *   Matching releases.
   */
  private function branchReleases(
    ProjectReleaseData $project,
    string $installedVersion,
    string $coreVersion,
  ): array {
    return array_values(array_filter(
      $project->releases,
      fn (ProjectRelease $release): bool => ProjectVersion::isSameMajor(
        $release->version,
        $installedVersion,
      ) && $this->isUsableRelease(
        $release,
        $coreVersion,
        $project->machineName === 'drupal',
      ),
    ));
  }

  /**
   * Finds the release matching the installed version.
   */
  private function findInstalledRelease(
    ProjectReleaseData $project,
    string $installedVersion,
  ): ?ProjectRelease {
    foreach ($project->releases as $release) {
      if ($release->version === $installedVersion) {
        return $release;
      }
    }
    return NULL;
  }

  /**
   * Returns whether a release is stable, published, and core-compatible.
   */
  private function isUsableRelease(
    ProjectRelease $release,
    string $coreVersion,
    bool $isCore,
  ): bool {
    return $release->status === 'published'
      && ProjectVersion::isStable($release->version)
      && ($isCore || (
        $release->coreCompatibility !== ''
        && $this->satisfies($coreVersion, $release->coreCompatibility)
      ));
  }

  /**
   * Returns whether a stable published release supports Drupal 12.
   */
  private function isDrupal12Release(ProjectRelease $release): bool {
    return $release->status === 'published'
      && ProjectVersion::isStable($release->version)
      && $release->coreCompatibility !== ''
      && $this->satisfies('12.0.0', $release->coreCompatibility);
  }

  /**
   * Safely evaluates a Composer version constraint.
   */
  private function satisfies(string $version, string $constraint): bool {
    try {
      return Semver::satisfies($version, $constraint);
    }
    catch (\UnexpectedValueException) {
      return FALSE;
    }
  }

  /**
   * Returns the greatest release version in a list.
   *
   * @param \Drupal\hm_core\Release\ProjectRelease[] $releases
   *   Releases to compare.
   */
  private function latest(array $releases): ?ProjectRelease {
    $latest = NULL;
    foreach ($releases as $release) {
      if ($latest === NULL
        || ProjectVersion::compare($release->version, $latest->version) > 0
      ) {
        $latest = $release;
      }
    }
    return $latest;
  }

}
