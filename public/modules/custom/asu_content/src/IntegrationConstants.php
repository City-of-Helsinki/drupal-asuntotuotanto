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

  public const INTEGRATION_TRIGGERS_PROJECT = [
    'field_publish_on_oikotie' => self::OIKOTIE_REQUIRED_FIELDS_PROJECT,
    'field_publish_on_etuovi' => self::ETUOVI_REQUIRED_FIELDS_PROJECT,
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
  public static function getRequiredApartmentFieldsForIntegration(string $integration_trigger, string $ownership_type) {
    $required_fields = self::INTEGRATION_TRIGGERS[$integration_trigger];
    $ownership_type_fields = match (strtolower($ownership_type)) {
      'hitas' => self::HITAS_PRICE_FIELDS,
      'haso' => self::HASO_PRICE_FIELDS,
      default => [],
    };
    $required_fields = array_merge($required_fields, $ownership_type_fields);
    return $required_fields;
  }

  /**
   * Get required fields for project integration.
   *
   * @param string $integration_trigger
   *   The field machine name of the integration boolean.
   *
   * @return array
   *   An array of required fields for the integration.
   */
  public static function getRequiredProjectFieldsForIntegration(string $integration_trigger) {
    return self::INTEGRATION_TRIGGERS_PROJECT[$integration_trigger];
  }

  public const ETUOVI_REQUIRED_FIELDS_PROJECT = [
    'field_holding_type',
    'field_building_type',
    'field_postal_code',
    'field_city',
  ];

  public const OIKOTIE_REQUIRED_FIELDS_PROJECT = [
    'field_housing_company',
    'field_estate_agent_email',
    'field_street_address',
    'field_postal_code',
    'field_city',
    'field_coordinate_lat',
    'field_coordinate_lon',
    'field_new_development_status',
    'field_building_type',
    'field_holding_type',
  ];

  public const ETUOVI_REQUIRED_FIELDS_APARTMENT = [
    'field_room_count',
  ];

  public const OIKOTIE_REQUIRED_FIELDS_APARTMENT = [
    'field_living_area',
    'field_financing_fee',
    'field_maintenance_fee',
    'field_water_fee',
    'field_parking_fee',
    'field_url',
  ];

  public const HITAS_PRICE_FIELDS = [
    'field_debt_free_sales_price',
    'field_sales_price',
  ];

  public const HASO_PRICE_FIELDS = [
    'field_right_of_occupancy_payment',
  ];

}
