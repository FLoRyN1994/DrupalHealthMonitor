<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_agent\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Site\Settings;
use Drupal\hm_agent\SignatureValidator;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests request signature validation.
 */
#[CoversClass(SignatureValidator::class)]
#[Group('hm_agent')]
final class SignatureValidatorTest extends UnitTestCase {

  /**
   * Current time used by the tests.
   */
  private const CURRENT_TIME = 1_798_000_000;

  /**
   * Shared test secret.
   */
  private const SECRET = 'a-long-random-test-secret';

  /**
   * The validator under test.
   */
  private SignatureValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $settings = new Settings(['hm_agent_secret' => self::SECRET]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(self::CURRENT_TIME);
    $this->validator = new SignatureValidator($settings, $time);
  }

  /**
   * Tests accepting a valid signature.
   */
  public function testValidSignature(): void {
    self::assertTrue($this->validator->isValid($this->createSignedRequest()));
  }

  /**
   * Tests rejecting a changed signature.
   */
  public function testInvalidSignature(): void {
    $request = $this->createSignedRequest();
    $request->headers->set('X-HM-Signature', str_repeat('0', 64));

    self::assertFalse($this->validator->isValid($request));
  }

  /**
   * Tests rejecting stale and future timestamps.
   */
  public function testTimestampWindow(): void {
    self::assertFalse($this->validator->isValid(
      $this->createSignedRequest(self::CURRENT_TIME - 301),
    ));
    self::assertFalse($this->validator->isValid(
      $this->createSignedRequest(self::CURRENT_TIME + 1),
    ));
    self::assertTrue($this->validator->isValid(
      $this->createSignedRequest(self::CURRENT_TIME - 300),
    ));
  }

  /**
   * Tests rejecting a request when no secret is configured.
   */
  public function testMissingSecret(): void {
    $settings = new Settings([]);
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturn(self::CURRENT_TIME);
    $validator = new SignatureValidator($settings, $time);

    self::assertFalse($validator->isValid($this->createSignedRequest()));
  }

  /**
   * Creates a correctly signed status request.
   */
  private function createSignedRequest(int $timestamp = self::CURRENT_TIME): Request {
    $message = $timestamp . "\n/hm-agent/status";
    $signature = hash_hmac('sha256', $message, self::SECRET);

    return Request::create('/hm-agent/status', 'GET', server: [
      'HTTP_X_HM_TIMESTAMP' => (string) $timestamp,
      'HTTP_X_HM_SIGNATURE' => $signature,
    ]);
  }

}
