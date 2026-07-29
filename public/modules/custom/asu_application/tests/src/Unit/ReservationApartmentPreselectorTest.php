<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_application\Service\ReservationApartmentPreselector;
use Drupal\Tests\UnitTestCase;

/**
 * Tests apartment preselection for HITAS post-period reservations.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Service\ReservationApartmentPreselector
 */
final class ReservationApartmentPreselectorTest extends UnitTestCase {

  /**
   * Apartment options keyed by node id, as built by the application form.
   *
   * @var array
   */
  private const APARTMENTS = [
    84 => 'A15 | 1h+k+s | 4 / 5 | 29,0 m2 | 300 000 € | 300 000 €',
    85 => 'A16 | 2h+k | 4 / 5 | 45,0 m2 | 400 000 € | 400 000 €',
  ];

  /**
   * Resolver returns the requested apartment when it is selectable.
   */
  public function testResolvesRequestedApartment(): void {
    $preselector = new ReservationApartmentPreselector();

    $this->assertSame(
      ['id' => '84', 'information' => self::APARTMENTS[84]],
      $preselector->resolve('84', self::APARTMENTS)
    );
  }

  /**
   * Resolver ignores apartments that are not part of the selectable options.
   */
  public function testReturnsNullForApartmentOutsideOptions(): void {
    $preselector = new ReservationApartmentPreselector();

    $this->assertNull($preselector->resolve('999', self::APARTMENTS));
  }

  /**
   * Resolver ignores non-numeric and empty input.
   *
   * - NULL request (no query parameter given).
   * - Empty string.
   * - Non-numeric value.
   */
  public function testReturnsNullForInvalidInput(): void {
    $preselector = new ReservationApartmentPreselector();

    $this->assertNull($preselector->resolve(NULL, self::APARTMENTS));
    $this->assertNull($preselector->resolve('', self::APARTMENTS));
    $this->assertNull($preselector->resolve('not-an-id', self::APARTMENTS));
    $this->assertNull($preselector->resolve('84abc', self::APARTMENTS));
  }

  /**
   * Resolver returns NULL when no apartments are selectable.
   */
  public function testReturnsNullWhenNoApartmentsAvailable(): void {
    $preselector = new ReservationApartmentPreselector();

    $this->assertNull($preselector->resolve('84', []));
  }

  /**
   * Edit redirect keeps a valid apartment query parameter.
   */
  public function testAppendsApartmentQueryToEditUrl(): void {
    $preselector = new ReservationApartmentPreselector();

    $this->assertSame(
      '/application/12/edit?apartment=84',
      $preselector->appendApartmentQuery('/application/12/edit', '84')
    );
  }

  /**
   * Edit redirect without an apartment query stays unchanged.
   *
   * - NULL apartment id.
   * - Empty apartment id.
   * - Non-numeric apartment id.
   */
  public function testDoesNotAppendInvalidApartmentQuery(): void {
    $preselector = new ReservationApartmentPreselector();

    $this->assertSame(
      '/application/12/edit',
      $preselector->appendApartmentQuery('/application/12/edit', NULL)
    );
    $this->assertSame(
      '/application/12/edit',
      $preselector->appendApartmentQuery('/application/12/edit', '')
    );
    $this->assertSame(
      '/application/12/edit',
      $preselector->appendApartmentQuery('/application/12/edit', 'abc')
    );
  }

  /**
   * Existing query string is preserved when appending apartment.
   */
  public function testPreservesExistingQueryWhenAppendingApartment(): void {
    $preselector = new ReservationApartmentPreselector();

    $this->assertSame(
      '/application/12/edit?foo=bar&apartment=84',
      $preselector->appendApartmentQuery('/application/12/edit?foo=bar', '84')
    );
  }

}
