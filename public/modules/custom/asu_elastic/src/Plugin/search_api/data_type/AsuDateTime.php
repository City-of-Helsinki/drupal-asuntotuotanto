<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Convert indexed datetime to correct datetime timezone.
 *
 * @SearchApiDataType(
 *   id = "asu_date_time",
 *   label = @Translation("Date time"),
 *   description = @Translation("Covert real date time"),
 *   default = "true",
 *   fallback_type = "string",
 * )
 */
class AsuDateTime extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if (empty($value)) {
      return '';
    }

    if (is_array($value)) {
      $dates = [];

      foreach ($value as $date) {
        $dates[] = asu_content_convert_datetime($date);
      }

      $newvalue = $dates;
    }
    else {
      $newvalue = asu_content_convert_datetime($value);
    }

    return $newvalue;
  }

}
