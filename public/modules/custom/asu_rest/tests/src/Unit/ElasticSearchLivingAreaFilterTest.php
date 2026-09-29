<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Unit;

use Drupal\asu_rest\Plugin\rest\resource\ElasticSearch;
use Drupal\Tests\UnitTestCase;

/**
 * Tests living area filtering helpers in the /elasticsearch resource.
 *
 * @group asu_rest
 */
final class ElasticSearchLivingAreaFilterTest extends UnitTestCase {

  /**
   * Tests living area bounds normalization.
   *
   * @dataProvider normalizeBoundsProvider
   */
  public function testNormalizeLivingAreaBoundsForFilter(array $request, ?float $expectedMin, ?float $expectedMax): void {
    $resource = $this->createResource();
    $method = new \ReflectionMethod(ElasticSearch::class, 'normalizeLivingAreaBoundsForFilter');
    $method->setAccessible(TRUE);

    [$actualMin, $actualMax] = $method->invoke($resource, $request);

    $this->assertSame($expectedMin, $actualMin);
    $this->assertSame($expectedMax, $actualMax);
  }

  /**
   * Provides normalization test cases for living area bounds.
   *
   * @return array<string, array{0: array<string, mixed>, 1: ?float, 2: ?float}>
   *   Request payload and expected min/max bounds.
   */
  public static function normalizeBoundsProvider(): array {
    return [
      'empty request has no bounds' => [[], NULL, NULL],
      'csv upper bound only' => [['living_area' => ',70'], NULL, 70.0],
      'csv lower bound only' => [['living_area' => '50,'], 50.0, NULL],
      'csv lower and upper bound' => [['living_area' => '50,80'], 50.0, 80.0],
      'array lower and upper bound' => [['living_area' => ['45', '90']], 45.0, 90.0],
      'explicit bounds override range' => [
        [
          'living_area' => '10,20',
          'living_area_min' => '40',
          'living_area_max' => '60',
        ],
        40.0,
        60.0,
      ],
      'single numeric shorthand means upper bound' => [['living_area' => '70'], NULL, 70.0],
      'zero and negative values are ignored' => [['living_area' => '-10,0'], NULL, NULL],
      'legacy aliases are supported' => [['area_min' => '35', 'area_max' => '95'], 35.0, 95.0],
    ];
  }

  /**
   * Tests living area range matching.
   *
   * @dataProvider matchesProvider
   */
  public function testMatchesLivingAreaFilter(?float $value, ?float $min, ?float $max, bool $expected): void {
    $resource = $this->createResource();
    $method = new \ReflectionMethod(ElasticSearch::class, 'matchesLivingAreaFilter');
    $method->setAccessible(TRUE);

    $actual = $method->invoke($resource, $value, $min, $max);

    $this->assertSame($expected, $actual);
  }

  /**
   * Provides matching test cases for living area filter checks.
   *
   * @return array<string, array{0: ?float, 1: ?float, 2: ?float, 3: bool}>
   *   Living area value, min, max, and expected match result.
   */
  public static function matchesProvider(): array {
    return [
      'no bounds always matches' => [55.0, NULL, NULL, TRUE],
      'missing value fails when bounds exist' => [NULL, NULL, 70.0, FALSE],
      'value inside upper bound matches' => [68.5, NULL, 70.0, TRUE],
      'value above upper bound does not match' => [72.0, NULL, 70.0, FALSE],
      'value below lower bound does not match' => [39.9, 40.0, NULL, FALSE],
      'value inside range matches' => [50.0, 40.0, 70.0, TRUE],
    ];
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
