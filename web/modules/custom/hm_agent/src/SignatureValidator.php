<?php

declare(strict_types=1);

namespace Drupal\hm_agent;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validates signed requests from the Health Monitor.
 */
class SignatureValidator {

  /**
   * Maximum accepted request age in seconds.
   */
  private const MAX_AGE = 300;

  /**
   * Constructs a signature validator.
   */
  public function __construct(
    private readonly Settings $settings,
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Determines whether a request has a current, valid signature.
   */
  public function isValid(Request $request): bool {
    $secret = $this->settings::get('hm_agent_secret', '');
    $timestamp = $request->headers->get('X-HM-Timestamp', '');
    $signature = $request->headers->get('X-HM-Signature', '');

    if (!is_string($secret) || $secret === '' || !ctype_digit($timestamp)) {
      return FALSE;
    }

    $request_age = $this->time->getCurrentTime() - (int) $timestamp;
    if ($request_age < 0 || $request_age > self::MAX_AGE) {
      return FALSE;
    }

    $message = $timestamp . "\n" . $request->getPathInfo();
    $expected_signature = hash_hmac('sha256', $message, $secret);

    return hash_equals($expected_signature, $signature);
  }

}
