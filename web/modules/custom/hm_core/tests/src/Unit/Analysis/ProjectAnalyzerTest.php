<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Unit\Analysis;

use Drupal\hm_core\Analysis\ProjectAnalyzer;
use Drupal\hm_core\Release\ProjectRelease;
use Drupal\hm_core\Release\ProjectReleaseData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests deterministic project finding detection.
 */
#[CoversClass(ProjectAnalyzer::class)]
#[Group('hm_core')]
final class ProjectAnalyzerTest extends TestCase {

  /**
   * Tests security evidence supersedes a regular update finding.
   */
  public function testSecurityAndUnsupportedRelease(): void {
    $project = $this->project(
      ['8.x-1.'],
      [
        $this->release('8.x-1.2', '^11', TRUE, ['Security update']),
        $this->release('8.x-1.0', '^11', FALSE, ['Insecure']),
        $this->release('2.0.0', '^12'),
      ],
    );

    $findings = (new ProjectAnalyzer())->analyze(
      $project,
      '8.x-1.0',
      '11.4.0',
    );

    self::assertSame(['security', 'unsupported'], array_keys($findings));
    self::assertSame('critical', $findings['security']->severity);
    self::assertSame('8.x-1.2', $findings['security']->recommendedVersion);
    self::assertSame('8.x-1.2', $findings['unsupported']->recommendedVersion);
    self::assertArrayNotHasKey('update', $findings);
    self::assertArrayNotHasKey('d12_blocker', $findings);
  }

  /**
   * Tests a newer stable release in the installed major line.
   */
  public function testRegularUpdate(): void {
    $project = $this->project(
      ['2.'],
      [
        $this->release('3.0.0', '^11 || ^12'),
        $this->release('2.2.0-beta1', '^11 || ^12'),
        $this->release('2.1.0', '^11 || ^12'),
        $this->release('2.0.0', '^11 || ^12'),
      ],
    );

    $findings = (new ProjectAnalyzer())->analyze(
      $project,
      '2.0.0',
      '11.4.0',
    );

    self::assertSame(['update'], array_keys($findings));
    self::assertSame('2.1.0', $findings['update']->recommendedVersion);
  }

  /**
   * Tests unsupported branches can recommend a supported release line.
   */
  public function testUnsupportedBranch(): void {
    $project = $this->project(
      ['2.'],
      [
        $this->release('2.0.0', '^11'),
        $this->release('1.5.0', '^11'),
      ],
    );

    $findings = (new ProjectAnalyzer())->analyze(
      $project,
      '1.5.0',
      '11.4.0',
      FALSE,
    );

    self::assertSame(['unsupported'], array_keys($findings));
    self::assertSame('high', $findings['unsupported']->severity);
    self::assertSame('2.0.0', $findings['unsupported']->recommendedVersion);
  }

  /**
   * Tests a project with known constraints and no Drupal 12 release.
   */
  public function testDrupal12Blocker(): void {
    $project = $this->project(
      ['1.'],
      [$this->release('1.0.0', '^10 || ^11')],
    );

    $findings = (new ProjectAnalyzer())->analyze(
      $project,
      '1.0.0',
      '11.4.0',
    );

    self::assertSame(['d12_blocker'], array_keys($findings));
    self::assertSame('high', $findings['d12_blocker']->severity);
  }

  /**
   * Tests missing compatibility metadata does not become a false blocker.
   */
  public function testUnknownCompatibilityIsConservative(): void {
    $project = $this->project(
      ['1.'],
      [$this->release('1.0.0', '')],
    );

    $findings = (new ProjectAnalyzer())->analyze(
      $project,
      '1.0.0',
      '11.4.0',
    );

    self::assertSame([], $findings);
  }

  /**
   * Tests core releases do not require a core compatibility constraint.
   */
  public function testCoreUpdate(): void {
    $project = new ProjectReleaseData(
      'Drupal core',
      'drupal',
      'project_core',
      'published',
      ['11.4.'],
      [
        $this->release('11.4.2', ''),
        $this->release('11.4.1', ''),
      ],
    );

    $findings = (new ProjectAnalyzer())->analyze(
      $project,
      '11.4.1',
      '11.4.1',
      FALSE,
    );

    self::assertSame('11.4.2', $findings['update']->recommendedVersion);
  }

  /**
   * Creates normalized project data for a test.
   *
   * @param string[] $supportedBranches
   *   Supported release branch prefixes.
   * @param \Drupal\hm_core\Release\ProjectRelease[] $releases
   *   Project releases.
   */
  private function project(
    array $supportedBranches,
    array $releases,
  ): ProjectReleaseData {
    return new ProjectReleaseData(
      'Example',
      'example',
      'project_module',
      'published',
      $supportedBranches,
      $releases,
    );
  }

  /**
   * Creates one published test release.
   *
   * @param string $version
   *   Project version.
   * @param string $coreCompatibility
   *   Core constraint.
   * @param bool $securityCovered
   *   Whether security coverage applies.
   * @param string[] $releaseTypes
   *   Drupal.org release type terms.
   */
  private function release(
    string $version,
    string $coreCompatibility,
    bool $securityCovered = TRUE,
    array $releaseTypes = [],
  ): ProjectRelease {
    return new ProjectRelease(
      $version,
      'published',
      'https://www.drupal.org/project/example/releases/' . $version,
      1_798_000_000,
      $coreCompatibility,
      $securityCovered,
      $releaseTypes,
    );
  }

}
