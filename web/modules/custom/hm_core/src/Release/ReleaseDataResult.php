<?php

declare(strict_types=1);

namespace Drupal\hm_core\Release;

/**
 * Explicit success or failure result from a release data lookup.
 */
final readonly class ReleaseDataResult {

  /**
   * Constructs a result.
   */
  private function __construct(
    public ?ProjectReleaseData $project,
    public ?string $errorCode,
    public ?string $errorMessage,
    public int $fetchedAt,
  ) {
  }

  /**
   * Creates a successful lookup result.
   */
  public static function success(ProjectReleaseData $project, int $fetchedAt): self {
    return new self($project, NULL, NULL, $fetchedAt);
  }

  /**
   * Creates an unavailable lookup result.
   */
  public static function unavailable(
    string $errorCode,
    string $errorMessage,
    int $fetchedAt,
  ): self {
    return new self(NULL, $errorCode, $errorMessage, $fetchedAt);
  }

  /**
   * Returns whether normalized project data is available.
   */
  public function isAvailable(): bool {
    return $this->project !== NULL;
  }

  /**
   * Returns a cache-safe array representation.
   *
   * @return array<string, mixed>
   *   Serializable result data.
   */
  public function toArray(): array {
    return [
      'project' => $this->project?->toArray(),
      'error_code' => $this->errorCode,
      'error_message' => $this->errorMessage,
      'fetched_at' => $this->fetchedAt,
    ];
  }

  /**
   * Reconstructs a result from cached data.
   *
   * @param array<string, mixed> $data
   *   Cached result data.
   */
  public static function fromArray(array $data): ?self {
    if (!is_int($data['fetched_at'] ?? NULL)) {
      return NULL;
    }

    if (is_array($data['project'] ?? NULL)) {
      $project = ProjectReleaseData::fromArray($data['project']);
      return $project === NULL
        ? NULL
        : self::success($project, $data['fetched_at']);
    }

    if (!is_string($data['error_code'] ?? NULL)
      || !is_string($data['error_message'] ?? NULL)
    ) {
      return NULL;
    }

    return self::unavailable(
      $data['error_code'],
      $data['error_message'],
      $data['fetched_at'],
    );
  }

}
