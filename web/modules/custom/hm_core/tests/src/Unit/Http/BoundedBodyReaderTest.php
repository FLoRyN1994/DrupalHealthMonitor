<?php

declare(strict_types=1);

namespace Drupal\Tests\hm_core\Unit\Http;

use Drupal\hm_core\Http\BoundedBodyReader;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests size-limited reading of remote response bodies.
 */
#[CoversClass(BoundedBodyReader::class)]
#[Group('hm_core')]
final class BoundedBodyReaderTest extends UnitTestCase {

  /**
   * Tests that bodies at or below the limit are returned unchanged.
   */
  public function testReturnsBodyWithinLimit(): void {
    self::assertSame('', BoundedBodyReader::read(Utils::streamFor(''), 10));
    self::assertSame('0123456789', BoundedBodyReader::read(Utils::streamFor('0123456789'), 10));
    $large = str_repeat('a', 200_000);
    self::assertSame($large, BoundedBodyReader::read(Utils::streamFor($large), 200_000));
  }

  /**
   * Tests that a declared oversized body is rejected.
   */
  public function testRejectsDeclaredOversizedBody(): void {
    self::assertNull(BoundedBodyReader::read(Utils::streamFor('01234567890'), 10));
  }

  /**
   * Tests that an endless body of unknown size is abandoned.
   */
  public function testRejectsEndlessBodyOfUnknownSize(): void {
    $reads = 0;
    $endless = new PumpStream(static function (int $length) use (&$reads): string {
      $reads++;
      return str_repeat('x', $length);
    });
    self::assertNull($endless->getSize());

    self::assertNull(BoundedBodyReader::read($endless, 100_000));
    self::assertLessThan(10, $reads);
  }

}
