<?php

namespace Drupal\asu_application\Service;

use Drupal\Core\Site\Settings;

/**
 * Whether applications may target apartments already marked as sold.
 */
final class SoldApartmentApplicationPolicy {

  /**
   * Settings key aligned with Django ALLOW_APPLICATIONS_TO_SOLD_APARTMENTS.
   */
  private const SETTING_KEY = 'allow_applications_to_sold_apartments';

  /**
   * Return TRUE when sold apartments may be included in applications.
   *
   * Enabled in non-production environments via settings.php (and optionally
   * ALLOW_APPLICATIONS_TO_SOLD_APARTMENTS env). Production defaults to FALSE.
   */
  public function allowsApplicationsToSoldApartments(): bool {
    return (bool) Settings::get(self::SETTING_KEY, FALSE);
  }

}
