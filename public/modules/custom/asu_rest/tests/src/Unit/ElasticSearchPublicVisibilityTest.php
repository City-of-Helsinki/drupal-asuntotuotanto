<?php

declare(strict_types=1);

namespace Drupal\Tests\asu_rest\Unit;

use Drupal\asu_rest\Plugin\rest\resource\ElasticSearch;
use PHPUnit\Framework\TestCase;

/**
 * Tests public apartment visibility rules for the elasticsearch endpoint.
 *
 * @group asu_rest
 */
final class ElasticSearchPublicVisibilityTest extends TestCase {

  /**
   * Sold apartments must not be exposed to end users via elasticsearch.
   *
   * @dataProvider apartmentStateOfSaleVisibilityProvider
   */
  public function testIsApartmentVisibleInPublicSearch(
    string $apartmentStateOfSale,
    bool $expected,
  ): void {
    $resource = $this->createElasticSearchResource();
    $method = new \ReflectionMethod(ElasticSearch::class, 'isApartmentVisibleInPublicSearch');
    $method->setAccessible(TRUE);

    $this->assertSame(
      $expected,
      $method->invoke($resource, $apartmentStateOfSale),
    );
  }

  /**
   * Data provider for apartment public visibility.
   */
  public static function apartmentStateOfSaleVisibilityProvider(): array {
    return [
      'sold is hidden' => ['SOLD', FALSE],
      'for sale is visible' => ['FOR_SALE', TRUE],
      'reserved is visible' => ['RESERVED', TRUE],
      'empty state is visible' => ['', TRUE],
    ];
  }

  /**
   * Creates an ElasticSearch resource instance without container wiring.
   */
  private function createElasticSearchResource(): ElasticSearch {
    $ref = new \ReflectionClass(ElasticSearch::class);
    /** @var \Drupal\asu_rest\Plugin\rest\resource\ElasticSearch $resource */
    $resource = $ref->newInstanceWithoutConstructor();

    return $resource;
  }

}
