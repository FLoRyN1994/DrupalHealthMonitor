<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Unit\Release;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\MemoryBackend;
use Drupal\hm_core\Release\DrupalOrgReleaseDataClient;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Psr\Log\NullLogger;

/**
 * Tests the cached drupal.org release data client.
 */
#[CoversClass(DrupalOrgReleaseDataClient::class)]
#[Group('hm_core')]
final class DrupalOrgReleaseDataClientTest extends UnitTestCase {

  /**
   * Mutable test clock value.
   */
  private int $currentTime = 1_700_000_000;

  /**
   * Captured HTTP request history.
   *
   * @var array<int, array<string, mixed>>
   */
  private array $history = [];

  /**
   * Tests normalization and successful response caching.
   */
  public function testSuccessfulResponseIsNormalizedAndCached(): void {
    $client = $this->createClient([
      new Response(200, [], $this->fixture('example.xml')),
      new Response(200, [], $this->fixture('example.xml')),
    ]);

    $first_result = $client->getProject('example');
    $this->currentTime += 21_600;
    $cached_result = $client->getProject('example');

    self::assertTrue($first_result->isAvailable());
    self::assertTrue($cached_result->isAvailable());
    self::assertSame(1_700_000_000, $cached_result->fetchedAt);
    self::assertNotNull($first_result->project);
    self::assertSame('Example module', $first_result->project->title);
    self::assertSame('example', $first_result->project->machineName);
    self::assertSame('project_module', $first_result->project->projectType);
    self::assertSame('published', $first_result->project->projectStatus);
    self::assertSame(['2.0.', '3.0.'], $first_result->project->supportedBranches);
    self::assertCount(2, $first_result->project->releases);

    $security_release = $first_result->project->releases[0];
    self::assertSame('3.0.1', $security_release->version);
    self::assertSame(1_793_355_985, $security_release->publishedAt);
    self::assertSame('^11 || ^12', $security_release->coreCompatibility);
    self::assertTrue($security_release->securityCovered);
    self::assertTrue($security_release->isSecurityUpdate());
    self::assertFalse($security_release->isInsecure());

    $insecure_release = $first_result->project->releases[1];
    self::assertNull($insecure_release->publishedAt);
    self::assertFalse($insecure_release->securityCovered);
    self::assertTrue($insecure_release->isInsecure());

    self::assertCount(1, $this->history);
    self::assertSame(
      'https://updates.drupal.org/release-history/example/current',
      (string) $this->history[0]['request']->getUri(),
    );
    self::assertSame(
      'application/xml, text/xml',
      $this->history[0]['request']->getHeaderLine('Accept'),
    );

    $this->currentTime++;
    $refreshed_result = $client->getProject('example');
    self::assertTrue($refreshed_result->isAvailable());
    self::assertSame(1_700_021_601, $refreshed_result->fetchedAt);
    self::assertCount(2, $this->history);
  }

  /**
   * Tests that a valid project with no releases is not treated as a failure.
   */
  public function testNoReleasesIsSuccessfulData(): void {
    $client = $this->createClient([
      new Response(200, [], $this->fixture('no-releases.xml')),
    ]);

    $result = $client->getProject('empty_project');

    self::assertTrue($result->isAvailable());
    self::assertNotNull($result->project);
    self::assertSame([], $result->project->supportedBranches);
    self::assertSame([], $result->project->releases);
  }

  /**
   * Tests that HTTP failures use the shorter negative cache lifetime.
   */
  public function testHttpFailureIsNegativelyCached(): void {
    $client = $this->createClient([
      new Response(503),
      new Response(200, [], $this->fixture('example.xml')),
    ]);

    $failure = $client->getProject('example');
    $this->currentTime += 300;
    $cached_failure = $client->getProject('example');

    self::assertFalse($failure->isAvailable());
    self::assertSame('http_error', $failure->errorCode);
    self::assertSame('drupal.org returned HTTP 503.', $failure->errorMessage);
    self::assertSame('http_error', $cached_failure->errorCode);
    self::assertCount(1, $this->history);

    $this->currentTime++;
    $success = $client->getProject('example');
    self::assertTrue($success->isAvailable());
    self::assertCount(2, $this->history);
  }

  /**
   * Tests transport failures remain distinct from empty project data.
   */
  public function testNetworkFailureIsExplicit(): void {
    $client = $this->createClient([
      new ConnectException('Connection refused.', new Request('GET', '/')),
    ]);

    $result = $client->getProject('example');

    self::assertFalse($result->isAvailable());
    self::assertSame('network_error', $result->errorCode);
    self::assertStringContainsString('Connection refused.', $result->errorMessage ?? '');
  }

  /**
   * Tests malformed and mismatched XML responses.
   */
  #[TestWith(['<project>'])]
  #[TestWith(['<project><short_name>other</short_name><project_status>published</project_status></project>'])]
  public function testInvalidXmlIsUnavailable(string $xml): void {
    $client = $this->createClient([new Response(200, [], $xml)]);

    $result = $client->getProject('example');

    self::assertFalse($result->isAvailable());
    self::assertSame('invalid_xml', $result->errorCode);
  }

  /**
   * Tests machine names before they reach the HTTP client.
   */
  public function testInvalidProjectNameIsRejected(): void {
    $client = $this->createClient([]);

    $this->expectException(\InvalidArgumentException::class);
    try {
      $client->getProject('../example');
    }
    finally {
      self::assertCount(0, $this->history);
    }
  }

  /**
   * Creates a client backed by queued HTTP responses.
   *
   * @param array<mixed> $responses
   *   Guzzle mock responses or exceptions.
   *
   * @return \Drupal\hm_core\Release\DrupalOrgReleaseDataClient
   *   The release data client.
   */
  private function createClient(array $responses): DrupalOrgReleaseDataClient {
    $this->history = [];
    $handler_stack = HandlerStack::create(new MockHandler($responses));
    $handler_stack->push(Middleware::history($this->history));

    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')
      ->willReturnCallback(fn (): int => $this->currentTime);
    $time->method('getRequestTime')
      ->willReturnCallback(fn (): int => $this->currentTime);

    $cache = new MemoryBackend($time);
    $client = new DrupalOrgReleaseDataClient(
      new Client(['handler' => $handler_stack]),
      $cache,
      $time,
      new NullLogger(),
    );

    return $client;
  }

  /**
   * Reads a release-history fixture.
   */
  private function fixture(string $filename): string {
    $contents = file_get_contents(__DIR__ . '/../../../fixtures/release-history/' . $filename);
    self::assertIsString($contents);
    return $contents;
  }

}
