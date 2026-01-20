<?php

namespace Drupal\asu_content;

use Drupal\Core\Entity\EntityInterface;

class IntegrationConstants {
  public const INTEGRATION_TRIGGERS = [
    'field_publish_on_oikotie' => self::OIKOTIE_REQUIRED_FIELDS_APARTMENT,
    'field_publish_on_etuovi' => self::ETUOVI_REQUIRED_FIELDS_APARTMENT,
  ];

  public static function getRequiredFieldsForIntegration($integration_trigger, string $ownership_type) {
    $required_fields = self::INTEGRATION_TRIGGERS[$integration_trigger];    
    $ownership_type_fields = match (strtolower($ownership_type)) {
      'hitas' => self::HITAS_PRICE_FIELDS,
      'haso' => self::HASO_PRICE_FIELDS,
      default => [],
    };
    $required_fields = array_merge($required_fields, $ownership_type_fields);
    \Drupal::logger('asu_content')->notice('getRequiredFieldsForIntegration Required fields: @required_fields, ownership type: @ownership_type', ['@required_fields' => $required_fields, '@ownership_type' => $ownership_type]);
    return $required_fields;
  }

  
  public const ETUOVI_REQUIRED_FIELDS_APARTMENT = [
    'field_room_count'
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
