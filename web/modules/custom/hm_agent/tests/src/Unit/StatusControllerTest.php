<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_agent\Unit;

use Drupal\hm_agent\Controller\StatusController;
use Drupal\hm_agent\SignatureValidator;
use Drupal\hm_agent\StatusCollector;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the status endpoint controller.
 */
#[CoversClass(StatusController::class)]
#[Group('hm_agent')]
final class StatusControllerTest extends UnitTestCase {

  /**
   * Tests that invalid authentication returns no status data.
   */
  public function testUnauthorizedRequest(): void {
    $request = Request::create('/hm-agent/status');
    $signature_validator = $this->createMock(SignatureValidator::class);
    $signature_validator->expects(self::once())
      ->method('isValid')
      ->with($request)
      ->willReturn(FALSE);
    $status_collector = $this->createMock(StatusCollector::class);
    $status_collector->expects(self::never())->method('collect');

    $response = (new StatusController($signature_validator, $status_collector))($request);

    self::assertSame(401, $response->getStatusCode());
    self::assertSame('{"error":"Unauthorized"}', $response->getContent());
    self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
  }

  /**
   * Tests that valid authentication returns the collected status.
   */
  public function testAuthorizedRequest(): void {
    $request = Request::create('/hm-agent/status');
    $signature_validator = $this->createMock(SignatureValidator::class);
    $signature_validator->method('isValid')->with($request)->willReturn(TRUE);
    $status_collector = $this->createMock(StatusCollector::class);
    $status_collector->expects(self::once())
      ->method('collect')
      ->willReturn(['schema_version' => 1]);

    $response = (new StatusController($signature_validator, $status_collector))($request);

    self::assertSame(200, $response->getStatusCode());
    self::assertSame('{"schema_version":1}', $response->getContent());
    self::assertTrue($response->headers->hasCacheControlDirective('no-store'));
  }

}
