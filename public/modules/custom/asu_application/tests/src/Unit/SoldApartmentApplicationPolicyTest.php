<?php

namespace Drupal\Tests\asu_application\Unit;

use Drupal\asu_application\Service\SoldApartmentApplicationPolicy;
use Drupal\Core\Site\Settings;
use Drupal\Tests\UnitTestCase;

/**
 * Tests sold-apartment application policy.
 *
 * @group asu_application
 *
 * @coversDefaultClass \Drupal\asu_application\Service\SoldApartmentApplicationPolicy
 */
final class SoldApartmentApplicationPolicyTest extends UnitTestCase {

  /**
   * Missing setting defaults to blocking sold-apartment applications.
   */
  public function testDefaultsToDisallowWhenSettingMissing(): void {
    new Settings([]);
    $policy = new SoldApartmentApplicationPolicy();
    $this->assertFalse($policy->allowsApplicationsToSoldApartments());
  }

  /**
   * Explicit setting enables the dev/test bypass.
   */
  public function testAllowsWhenSettingEnabled(): void {
    new Settings(['allow_applications_to_sold_apartments' => TRUE]);
    $policy = new SoldApartmentApplicationPolicy();
    $this->assertTrue($policy->allowsApplicationsToSoldApartments());
  }

}
