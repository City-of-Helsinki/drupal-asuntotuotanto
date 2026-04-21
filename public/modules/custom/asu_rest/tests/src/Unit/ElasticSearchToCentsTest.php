<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Unit;

use Drupal\asu_rest\Plugin\rest\resource\ElasticSearch;
use Drupal\Tests\UnitTestCase;

/**
 * Tests cents conversion used by the /elasticsearch resource.
 *
 * @group asu_rest
 */
final class ElasticSearchToCentsTest extends UnitTestCase {

  /**
   * @dataProvider centsProvider
   */
  public function testToCents(?string $input, int $expected): void {
    $ref = new \ReflectionClass(ElasticSearch::class);
    /** @var \Drupal\asu_rest\Plugin\rest\resource\ElasticSearch $resource */
    $resource = $ref->newInstanceWithoutConstructor();

    $method = new \ReflectionMethod(ElasticSearch::class, 'toCents');
    $method->setAccessible(TRUE);

    $actual = $method->invoke($resource, $input);
    $this->assertSame($expected, $actual);
  }

  /**
   * Data provider for cents conversion.
   *
   * @return array<string, array{0: ?string, 1: int}>
   */
  public static function centsProvider(): array {
    return [
      'null becomes zero' => [NULL, 0],
      'empty becomes zero' => ['', 0],
      'zero becomes zero' => ['0', 0],
      'integer euros' => ['123', 12300],
      'two decimals' => ['123.45', 12345],
      'one decimal rounds' => ['123.4', 12340],
      'round half up-ish' => ['0.005', 1],
    ];
  }

}

