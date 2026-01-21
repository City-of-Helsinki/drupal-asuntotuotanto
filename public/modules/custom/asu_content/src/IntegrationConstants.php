<?php

namespace Drupal\asu_content;

/**
 * Integration constants.
 *
 * Class for storing lists of mandatory fields for integrations.
 */
class IntegrationConstants {
  /**
   * Integration triggers.
   */
  public const INTEGRATION_TRIGGERS = [
    'field_publish_on_oikotie' => self::OIKOTIE_REQUIRED_FIELDS_APARTMENT,
    'field_publish_on_etuovi' => self::ETUOVI_REQUIRED_FIELDS_APARTMENT,
  ];

  /**
   * Get required fields for integration.
   *
   * @param string $integration_trigger
   *   The field machine name of the integration boolean.
   * @param string $ownership_type
   *   The ownership type.
   *
   * @return array
   *   An array of required fields for the integration.
   */
  public static function getRequiredFieldsForIntegration(string $integration_trigger, string $ownership_type) {
    $required_fields = self::INTEGRATION_TRIGGERS[$integration_trigger];
    $ownership_type_fields = match (strtolower($ownership_type)) {
      'hitas' => self::HITAS_PRICE_FIELDS,
      'haso' => self::HASO_PRICE_FIELDS,
      default => [],
    };
    $required_fields = array_merge($required_fields, $ownership_type_fields);
    return $required_fields;
  }

  public const ETUOVI_REQUIRED_FIELDS_APARTMENT = [
    'field_room_count',
  ];

  public const OIKOTIE_REQUIRED_FIELDS_APARTMENT = [
    'field_application_url',
  ];

  public const HITAS_PRICE_FIELDS = [
    'field_debt_free_sales_price',
    'field_sales_price',
  ];

  public const HASO_PRICE_FIELDS = [
    'field_right_of_occupancy_payment',
  ];

}
