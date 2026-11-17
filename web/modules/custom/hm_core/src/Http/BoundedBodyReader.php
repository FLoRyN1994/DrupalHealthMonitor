<?php

declare(strict_types=1);

namespace Drupal\hm_core\Http;

use Psr\Http\Message\StreamInterface;

/**
 * Reads remote response bodies without allowing unbounded memory use.
 */
final class BoundedBodyReader {

  /**
   * Bytes read from the stream per iteration.
   */
  private const CHUNK_BYTES = 65_536;

  /**
   * Returns the body, or NULL when it exceeds the byte limit.
   *
   * The stream is consumed incrementally so an oversized or endless response
   * is abandoned after at most one chunk beyond the limit, instead of being
   * buffered completely before its size is checked.
   */
  public static function read(StreamInterface $body, int $maxBytes): ?string {
    $declaredSize = $body->getSize();
    if ($declaredSize !== NULL && $declaredSize > $maxBytes) {
      return NULL;
    }

    $contents = '';
    while (!$body->eof()) {
      $chunk = $body->read(self::CHUNK_BYTES);
      if ($chunk === '') {
        break;
      }
      $contents .= $chunk;
      if (strlen($contents) > $maxBytes) {
        return NULL;
      }
    }

    return $contents;
  }

}
