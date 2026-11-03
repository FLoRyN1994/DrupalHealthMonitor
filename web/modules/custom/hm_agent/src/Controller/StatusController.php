<?php

declare(strict_types=1);

namespace Drupal\hm_agent\Controller;

use Drupal\hm_agent\SignatureValidator;
use Drupal\hm_agent\StatusCollector;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns the signed Health Monitor status response.
 */
class StatusController {

  /**
   * Constructs the status controller.
   */
  public function __construct(
    private readonly SignatureValidator $signatureValidator,
    private readonly StatusCollector $statusCollector,
  ) {
  }

  /**
   * Returns the current site status when authentication succeeds.
   */
  public function __invoke(Request $request): JsonResponse {
    if (!$this->signatureValidator->isValid($request)) {
      return new JsonResponse(['error' => 'Unauthorized'], 401, [
        'Cache-Control' => 'no-store',
      ]);
    }

    return new JsonResponse($this->statusCollector->collect(), 200, [
      'Cache-Control' => 'no-store',
    ]);
  }

}
