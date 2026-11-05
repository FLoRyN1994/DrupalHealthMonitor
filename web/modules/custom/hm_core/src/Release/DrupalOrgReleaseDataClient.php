<?php

declare(strict_types=1);

namespace Drupal\hm_core\Release;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * Fetches and caches normalized release history from drupal.org.
 */
final class DrupalOrgReleaseDataClient implements ReleaseDataClientInterface {

  /**
   * Successful response cache lifetime: six hours.
   */
  private const SUCCESS_TTL = 21_600;

  /**
   * Failed response cache lifetime: five minutes.
   */
  private const FAILURE_TTL = 300;

  /**
   * Maximum accepted release-history response size.
   */
  private const MAX_RESPONSE_BYTES = 5_000_000;

  /**
   * Constructs the release data client.
   */
  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly CacheBackendInterface $cache,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getProject(string $projectName): ReleaseDataResult {
    if (!preg_match('/^[a-z0-9_]{1,128}$/', $projectName)) {
      throw new \InvalidArgumentException(sprintf(
        'Invalid drupal.org project machine name "%s".',
        $projectName,
      ));
    }

    $cache_id = 'hm_core:release_data:' . $projectName;
    $cached = $this->cache->get($cache_id);
    if ($cached !== FALSE && is_array($cached->data)) {
      $cached_result = ReleaseDataResult::fromArray($cached->data);
      if ($cached_result !== NULL) {
        return $cached_result;
      }
      $this->cache->delete($cache_id);
    }

    $fetched_at = $this->time->getCurrentTime();
    $url = sprintf(
      'https://updates.drupal.org/release-history/%s/current',
      rawurlencode($projectName),
    );

    try {
      $response = $this->httpClient->request('GET', $url, [
        'connect_timeout' => 5,
        'headers' => ['Accept' => 'application/xml, text/xml'],
        'http_errors' => FALSE,
        'timeout' => 15,
      ]);
    }
    catch (GuzzleException $exception) {
      return $this->cacheFailure(
        $cache_id,
        $projectName,
        'network_error',
        $exception->getMessage(),
        $fetched_at,
      );
    }

    $http_status = $response->getStatusCode();
    if ($http_status < 200 || $http_status >= 300) {
      return $this->cacheFailure(
        $cache_id,
        $projectName,
        'http_error',
        sprintf('drupal.org returned HTTP %d.', $http_status),
        $fetched_at,
      );
    }

    $raw_xml = (string) $response->getBody();
    if (strlen($raw_xml) > self::MAX_RESPONSE_BYTES) {
      return $this->cacheFailure(
        $cache_id,
        $projectName,
        'response_too_large',
        'The release-history response exceeded 5 MB.',
        $fetched_at,
      );
    }

    $project = $this->parseProject($raw_xml, $projectName);
    if ($project === NULL) {
      return $this->cacheFailure(
        $cache_id,
        $projectName,
        'invalid_xml',
        'The release-history response was malformed or for another project.',
        $fetched_at,
      );
    }

    $result = ReleaseDataResult::success($project, $fetched_at);
    $this->cache->set(
      $cache_id,
      $result->toArray(),
      $fetched_at + self::SUCCESS_TTL,
      ['hm_core:release_data', 'hm_core:release_data:' . $projectName],
    );

    return $result;
  }

  /**
   * Parses and normalizes one release-history XML document.
   */
  private function parseProject(
    string $rawXml,
    string $expectedProjectName,
  ): ?ProjectReleaseData {
    $previous_error_mode = libxml_use_internal_errors(TRUE);
    libxml_clear_errors();
    $xml = simplexml_load_string(
      $rawXml,
      \SimpleXMLElement::class,
      LIBXML_NONET | LIBXML_NOBLANKS,
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous_error_mode);

    if (!$xml instanceof \SimpleXMLElement
      || (string) $xml->short_name !== $expectedProjectName
      || (string) $xml->project_status === ''
    ) {
      return NULL;
    }

    $supported_branches = array_values(array_filter(
      array_map(
        'trim',
        explode(',', (string) $xml->supported_branches),
      ),
      static fn (string $branch): bool => $branch !== '',
    ));

    $releases = [];
    foreach ($xml->releases->release ?? [] as $release_xml) {
      $version = trim((string) $release_xml->version);
      if ($version === '') {
        continue;
      }

      $release_types = [];
      foreach ($release_xml->terms->term ?? [] as $term_xml) {
        if ((string) $term_xml->name === 'Release type') {
          $release_type = trim((string) $term_xml->value);
          if ($release_type !== '') {
            $release_types[] = $release_type;
          }
        }
      }

      $date = (string) $release_xml->date;
      $releases[] = new ProjectRelease(
        $version,
        (string) $release_xml->status,
        (string) $release_xml->release_link,
        ctype_digit($date) ? (int) $date : NULL,
        (string) $release_xml->core_compatibility,
        (string) $release_xml->security['covered'] === '1',
        array_values(array_unique($release_types)),
      );
    }

    return new ProjectReleaseData(
      (string) $xml->title,
      (string) $xml->short_name,
      (string) $xml->type,
      (string) $xml->project_status,
      $supported_branches,
      $releases,
    );
  }

  /**
   * Caches and logs an unavailable lookup result.
   */
  private function cacheFailure(
    string $cacheId,
    string $projectName,
    string $errorCode,
    string $errorMessage,
    int $fetchedAt,
  ): ReleaseDataResult {
    $result = ReleaseDataResult::unavailable(
      $errorCode,
      $errorMessage,
      $fetchedAt,
    );
    $this->cache->set(
      $cacheId,
      $result->toArray(),
      $fetchedAt + self::FAILURE_TTL,
      ['hm_core:release_data', 'hm_core:release_data:' . $projectName],
    );
    $this->logger->warning(
      'Could not retrieve release data for @project: @message',
      [
        '@project' => $projectName,
        '@message' => $errorMessage,
      ],
    );

    return $result;
  }

}
