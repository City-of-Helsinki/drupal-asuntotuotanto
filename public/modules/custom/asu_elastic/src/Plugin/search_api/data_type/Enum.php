<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Enum.
 *
 * @SearchApiDataType(
 *   id = "asu_enum",
 *   label = @Translation("Enum"),
 *   description = @Translation("Enum"),
 *   fallback_type = "string",
 * )
 */
class Enum extends DataTypePluginBase {
  /**
   * {@inheritdoc}
   */
  public function getValue($value) {


    if ($value) {
      $string = strtoupper(
        str_replace(' ', '_', $value)
      );
      $string = str_replace('-', '_', $string);
      return $string;
    } else {
      return '';
    }
  }

}
