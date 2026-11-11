<?php

declare(strict_types=1);

namespace Drupal\hm_core\Release;

/**
 * Normalized metadata for one published project release.
 */
final readonly class ProjectRelease {

  /**
   * Constructs a project release.
   *
   * @param string $version
   *   Release version.
   * @param string $status
   *   Release publication status.
   * @param string $releaseLink
   *   Canonical drupal.org release URL.
   * @param int|null $publishedAt
   *   Release timestamp, when supplied.
   * @param string $coreCompatibility
   *   Drupal core version constraint.
   * @param bool $securityCovered
   *   Whether the release has security advisory coverage.
   * @param string[] $releaseTypes
   *   Release type terms supplied by drupal.org.
   */
  public function __construct(
    public string $version,
    public string $status,
    public string $releaseLink,
    public ?int $publishedAt,
    public string $coreCompatibility,
    public bool $securityCovered,
    public array $releaseTypes,
  ) {
  }

  /**
   * Returns whether drupal.org marks this as a security update.
   */
  public function isSecurityUpdate(): bool {
    return in_array('Security update', $this->releaseTypes, TRUE);
  }

  /**
   * Returns whether drupal.org marks this release as insecure.
   */
  public function isInsecure(): bool {
    return in_array('Insecure', $this->releaseTypes, TRUE);
  }

  /**
   * Returns a cache-safe array representation.
   *
   * @return array<string, mixed>
   *   Serializable release data.
   */
  public function toArray(): array {
    return [
      'version' => $this->version,
      'status' => $this->status,
      'release_link' => $this->releaseLink,
      'published_at' => $this->publishedAt,
      'core_compatibility' => $this->coreCompatibility,
      'security_covered' => $this->securityCovered,
      'release_types' => $this->releaseTypes,
    ];
  }

  /**
   * Reconstructs a release from cached data.
   *
   * @param array<string, mixed> $data
   *   Cached release data.
   */
  public static function fromArray(array $data): ?self {
    if (!is_string($data['version'] ?? NULL)
      || $data['version'] === ''
      || !is_string($data['status'] ?? NULL)
      || !is_string($data['release_link'] ?? NULL)
      || !is_string($data['core_compatibility'] ?? NULL)
      || !is_bool($data['security_covered'] ?? NULL)
      || !is_array($data['release_types'] ?? NULL)
    ) {
      return NULL;
    }

    $published_at = $data['published_at'] ?? NULL;
    if ($published_at !== NULL && !is_int($published_at)) {
      return NULL;
    }

    $release_types = [];
    foreach ($data['release_types'] as $release_type) {
      if (!is_string($release_type)) {
        return NULL;
      }
      $release_types[] = $release_type;
    }

    return new self(
      $data['version'],
      $data['status'],
      $data['release_link'],
      $published_at,
      $data['core_compatibility'],
      $data['security_covered'],
      $release_types,
    );
  }

}
