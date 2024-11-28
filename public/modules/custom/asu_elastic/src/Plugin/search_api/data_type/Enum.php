<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Turn taxonomy into enum.
 *
 * @SearchApiDataType(
 *   id = "asu_enum",
 *   label = @Translation("Enum"),
 *   description = @Translation("Enum"),
 *   default = "true",
 *   fallback_type = "string",
 * )
 */
class Enum extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    $exceptions = ['apartment_for_sale' => 'for_sale'];
    if ($value) {
      foreach ($exceptions as $key => $exception) {
        $value = $key == $value ? $exceptions[$value] : $value;
      }
      $string = strtoupper(
        str_replace(' ', '_', $value)
      );
      $string = str_replace('-', '_', $string);
      return $string;
    }
    else {
      return '';
    }
  }

}
