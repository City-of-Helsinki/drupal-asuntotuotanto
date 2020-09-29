<?php

namespace Drupal\asu_content\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a computed_array_decimal data type.
 *
 * @SearchApiDataType(
 *   id = "computed_array_decimal",
 *   label = @Translation("Computed array to decimal"),
 *   description = @Translation("Computed array to decimal fields are used for converting markup like render array to decimal."),
 *   fallback_type = "decimal",
 * )
 */
class ComputedArrayToDecimalDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    $value = (float) $value;
    if (!strpos((string) $value, '.')) {
      return (int) ($value * 100);
    }
    return (int) ($value * 100);
  }

}
