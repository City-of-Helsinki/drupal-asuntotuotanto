<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Unit;

use Drupal\asu_rest\Plugin\rest\resource\ElasticSearch;
use Drupal\Tests\UnitTestCase;

/**
 * Tests floor range filtering helpers in the /elasticsearch resource.
 *
 * @group asu_rest
 */
final class ElasticSearchFloorFilterTest extends UnitTestCase {

  /**
   * Tests floor bounds normalization and validation.
   *
   * @dataProvider normalizeFloorBoundsProvider
   */
  public function testNormalizeFloorBoundsForFilter(array $request, ?int $expectedMin, ?int $expectedMax, ?string $expectedError): void {
    $resource = $this->createResource();
    $method = new \ReflectionMethod(ElasticSearch::class, 'normalizeFloorBoundsForFilter');
    $method->setAccessible(TRUE);

    [$actualMin, $actualMax, $actualError] = $method->invoke($resource, $request);

    $this->assertSame($expectedMin, $actualMin);
    $this->assertSame($expectedMax, $actualMax);
    $this->assertSame($expectedError, $actualError);
  }

  /**
   * Provides floor normalization/validation test cases.
   *
   * @return array<string, array{0: array<string, mixed>, 1: ?int, 2: ?int, 3: ?string}>
   *   Request payload and expected min/max/error.
   */
  public static function normalizeFloorBoundsProvider(): array {
    return [
      'empty request has no bounds' => [[], NULL, NULL, NULL],
      'min only as string' => [['floor_min' => '2'], 2, NULL, NULL],
      'max only as int' => [['floor_max' => 5], NULL, 5, NULL],
      'both bounds as integers' => [
        ['floor_min' => '1', 'floor_max' => '4'],
        1,
        4,
        NULL,
      ],
      'empty strings are ignored' => [['floor_min' => '', 'floor_max' => ''], NULL, NULL, NULL],
      'negative floor is allowed' => [
        ['floor_min' => '-1', 'floor_max' => '2'],
        -1,
        2,
        NULL,
      ],
      'invalid floor_min value' => [
        ['floor_min' => 'abc'],
        NULL,
        NULL,
        'Invalid floor_min. Expected integer value.',
      ],
      'invalid floor_max decimal string' => [
        ['floor_max' => '3.5'],
        NULL,
        NULL,
        'Invalid floor_max. Expected integer value.',
      ],
      'floor min greater than max' => [
        ['floor_min' => '6', 'floor_max' => '3'],
        NULL,
        NULL,
        'Invalid floor range: floor_min cannot be greater than floor_max.',
      ],
    ];
  }

  /**
   * Tests floor bounds matching.
   *
   * @dataProvider matchesFloorBoundsProvider
   */
  public function testMatchesFloorBoundsFilter(?int $floor, ?int $min, ?int $max, bool $expected): void {
    $resource = $this->createResource();
    $method = new \ReflectionMethod(ElasticSearch::class, 'matchesFloorBoundsFilter');
    $method->setAccessible(TRUE);

    $actual = $method->invoke($resource, $floor, $min, $max);

    $this->assertSame($expected, $actual);
  }

  /**
   * Provides floor range match test cases.
   *
   * @return array<string, array{0: ?int, 1: ?int, 2: ?int, 3: bool}>
   *   Floor value, min, max and expected match.
   */
  public static function matchesFloorBoundsProvider(): array {
    return [
      'no bounds always matches' => [NULL, NULL, NULL, TRUE],
      'missing floor fails when min set' => [NULL, 2, NULL, FALSE],
      'floor equals min matches' => [2, 2, NULL, TRUE],
      'floor below min does not match' => [1, 2, NULL, FALSE],
      'floor equals max matches' => [5, NULL, 5, TRUE],
      'floor above max does not match' => [6, NULL, 5, FALSE],
      'floor inside range matches' => [3, 2, 5, TRUE],
      'floor outside range does not match' => [7, 2, 5, FALSE],
    ];
  }

  /**
   * Existing non-floor filter helpers keep their current behavior.
   */
  public function testLivingAreaNormalizationRegression(): void {
    $resource = $this->createResource();
    $method = new \ReflectionMethod(ElasticSearch::class, 'normalizeLivingAreaBoundsForFilter');
    $method->setAccessible(TRUE);

    [$min, $max] = $method->invoke($resource, ['living_area' => '50,80']);

    $this->assertSame(50.0, $min);
    $this->assertSame(80.0, $max);
  }

  /**
   * Creates an ElasticSearch resource instance without container wiring.
   */
  private function createResource(): ElasticSearch {
    $ref = new \ReflectionClass(ElasticSearch::class);
    /** @var \Drupal\asu_rest\Plugin\rest\resource\ElasticSearch $resource */
    $resource = $ref->newInstanceWithoutConstructor();

    return $resource;
  }

}
