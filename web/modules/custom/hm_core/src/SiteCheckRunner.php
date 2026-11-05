<?php

declare(strict_types=1);

namespace Drupal\hm_core;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\hm_core\Analysis\HealthScoreUpdater;
use Drupal\hm_core\Analysis\SnapshotAnalyzer;
use Drupal\hm_core\Entity\Site;
use Drupal\hm_core\Entity\Snapshot;
use Drupal\key\KeyInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Fetches and persists one monitored site's current status.
 */
final class SiteCheckRunner {

  /**
   * Agent endpoint path, included in the signed message.
   */
  private const AGENT_PATH = '/hm-agent/status';

  /**
   * Constructs a site-check runner.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Connection $database,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
    private readonly SnapshotAnalyzer $snapshotAnalyzer,
    private readonly HealthScoreUpdater $healthScoreUpdater,
  ) {
  }

  /**
   * Fetches a site, records uptime, and saves a valid snapshot.
   */
  public function check(int $siteId): void {
    $site = $this->entityTypeManager->getStorage('hm_site')->load($siteId);
    if (!$site instanceof Site || $site->get('status')->value !== 'active') {
      return;
    }

    $key = $site->get('agent_key')->referencedEntities()[0] ?? NULL;
    $secret = $key instanceof KeyInterface ? $key->getKeyValue() : NULL;
    if (!is_string($secret) || $secret === '') {
      $this->logger->warning('Site @site has no usable agent key.', [
        '@site' => $site->label(),
      ]);
      return;
    }

    $timestamp = $this->time->getCurrentTime();
    $signature = hash_hmac(
      'sha256',
      $timestamp . "\n" . self::AGENT_PATH,
      $secret,
    );
    $url = rtrim((string) $site->get('base_url')->value, '/') . self::AGENT_PATH;
    $started_at = $this->time->getCurrentMicroTime();

    try {
      $response = $this->httpClient->request('GET', $url, [
        'connect_timeout' => 5,
        'headers' => [
          'Accept' => 'application/json',
          'X-HM-Timestamp' => (string) $timestamp,
          'X-HM-Signature' => $signature,
        ],
        'http_errors' => FALSE,
        'timeout' => 15,
      ]);
    }
    catch (GuzzleException $exception) {
      $this->recordUptime(
        $siteId,
        $timestamp,
        0,
        $this->elapsedMilliseconds($started_at),
        FALSE,
        $exception->getMessage(),
      );
      $this->healthScoreUpdater->update($siteId);
      $this->logger->warning('Could not check @site: @message', [
        '@site' => $site->label(),
        '@message' => $exception->getMessage(),
      ]);
      return;
    }

    $http_status = $response->getStatusCode();
    $response_time = $this->elapsedMilliseconds($started_at);
    $raw_payload = (string) $response->getBody();
    $error = NULL;
    $snapshot = NULL;

    if ($http_status >= 200 && $http_status < 300) {
      try {
        $payload = json_decode($raw_payload, TRUE, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || !$this->isValidPayload($payload)) {
          throw new \UnexpectedValueException('The agent returned an invalid status payload.');
        }
        $snapshot = $this->saveSnapshot($siteId, $timestamp, $payload, $raw_payload);
      }
      catch (\JsonException | \UnexpectedValueException $exception) {
        $error = $exception->getMessage();
        $this->logger->warning('Invalid agent response from @site: @message', [
          '@site' => $site->label(),
          '@message' => $error,
        ]);
      }
    }
    else {
      $error = sprintf('Agent endpoint returned HTTP %d.', $http_status);
      $this->logger->warning('@site agent request returned HTTP @status.', [
        '@site' => $site->label(),
        '@status' => $http_status,
      ]);
    }

    $this->recordUptime(
      $siteId,
      $timestamp,
      $http_status,
      $response_time,
      $http_status >= 200 && $http_status < 300,
      $error,
    );
    if ($snapshot instanceof Snapshot) {
      $this->snapshotAnalyzer->analyze($snapshot);
    }
    $this->healthScoreUpdater->update($siteId);
  }

  /**
   * Validates the versioned status payload shape.
   *
   * @param array<mixed> $payload
   *   Decoded agent response.
   */
  private function isValidPayload(array $payload): bool {
    return ($payload['schema_version'] ?? NULL) === 1
      && is_string($payload['core_version'] ?? NULL)
      && $payload['core_version'] !== ''
      && is_string($payload['php_version'] ?? NULL)
      && $payload['php_version'] !== ''
      && is_array($payload['modules'] ?? NULL)
      && is_int($payload['pending_updates'] ?? NULL)
      && $payload['pending_updates'] >= 0;
  }

  /**
   * Saves an append-only snapshot entity.
   *
   * @param int $siteId
   *   Monitored site entity ID.
   * @param int $checkedAt
   *   Check timestamp.
   * @param array<mixed> $payload
   *   Validated agent response.
   * @param string $rawPayload
   *   Original response body.
   */
  private function saveSnapshot(
    int $siteId,
    int $checkedAt,
    array $payload,
    string $rawPayload,
  ): Snapshot {
    $snapshot = $this->entityTypeManager->getStorage('hm_snapshot')->create([
      'site' => $siteId,
      'checked_at' => $checkedAt,
      'core_version' => $payload['core_version'],
      'php_version' => $payload['php_version'],
      'modules' => json_encode($payload['modules'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
      'pending_updates' => $payload['pending_updates'],
      'raw_payload' => $rawPayload,
    ]);
    if (!$snapshot instanceof Snapshot) {
      throw new \UnexpectedValueException('Snapshot storage returned an invalid entity.');
    }
    $snapshot->save();
    return $snapshot;
  }

  /**
   * Inserts an uptime result.
   */
  private function recordUptime(
    int $siteId,
    int $checkedAt,
    int $httpStatus,
    int $responseTime,
    bool $success,
    ?string $error,
  ): void {
    $this->database->insert('hm_uptime_check')
      ->fields([
        'site_id' => $siteId,
        'checked_at' => $checkedAt,
        'http_status' => $httpStatus,
        'response_time_ms' => $responseTime,
        'success' => (int) $success,
        'error' => $error,
      ])
      ->execute();
  }

  /**
   * Returns elapsed wall-clock time in milliseconds.
   */
  private function elapsedMilliseconds(float $startedAt): int {
    return max(0, (int) round(($this->time->getCurrentMicroTime() - $startedAt) * 1_000));
  }

}
