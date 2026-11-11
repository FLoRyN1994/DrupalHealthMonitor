<?php

declare(strict_types=1);

namespace Drupal\hm_core\Release;

/**
 * Normalized drupal.org release history for one project.
 */
final readonly class ProjectReleaseData {

  /**
   * Constructs project release data.
   *
   * @param string $title
   *   Human-readable project title.
   * @param string $machineName
   *   Drupal.org project machine name.
   * @param string $projectType
   *   Drupal.org project type.
   * @param string $projectStatus
   *   Project publication and support status.
   * @param string[] $supportedBranches
   *   Branches currently supported by the project maintainers.
   * @param \Drupal\hm_core\Release\ProjectRelease[] $releases
   *   Published releases in the order returned by drupal.org.
   */
  public function __construct(
    public string $title,
    public string $machineName,
    public string $projectType,
    public string $projectStatus,
    public array $supportedBranches,
    public array $releases,
  ) {
  }

  /**
   * Returns a cache-safe array representation.
   *
   * @return array<string, mixed>
   *   Serializable project data.
   */
  public function toArray(): array {
    return [
      'title' => $this->title,
      'machine_name' => $this->machineName,
      'project_type' => $this->projectType,
      'project_status' => $this->projectStatus,
      'supported_branches' => $this->supportedBranches,
      'releases' => array_map(
        static fn (ProjectRelease $release): array => $release->toArray(),
        $this->releases,
      ),
    ];
  }

  /**
   * Reconstructs project data from cached data.
   *
   * @param array<string, mixed> $data
   *   Cached project data.
   */
  public static function fromArray(array $data): ?self {
    if (!is_string($data['title'] ?? NULL)
      || !is_string($data['machine_name'] ?? NULL)
      || $data['machine_name'] === ''
      || !is_string($data['project_type'] ?? NULL)
      || !is_string($data['project_status'] ?? NULL)
      || !is_array($data['supported_branches'] ?? NULL)
      || !is_array($data['releases'] ?? NULL)
    ) {
      return NULL;
    }

    $supported_branches = [];
    foreach ($data['supported_branches'] as $supported_branch) {
      if (!is_string($supported_branch)) {
        return NULL;
      }
      $supported_branches[] = $supported_branch;
    }

    $releases = [];
    foreach ($data['releases'] as $release_data) {
      if (!is_array($release_data)) {
        return NULL;
      }
      $release = ProjectRelease::fromArray($release_data);
      if ($release === NULL) {
        return NULL;
      }
      $releases[] = $release;
    }

    return new self(
      $data['title'],
      $data['machine_name'],
      $data['project_type'],
      $data['project_status'],
      $supported_branches,
      $releases,
    );
  }

}
