<?php

namespace Drupal\asu_content\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a computed_array_string data type.
 *
 * @SearchApiDataType(
 *   id = "computed_array_string",
 *   label = @Translation("Computed array to string"),
 *   description = @Translation("Computed array to string fields are used for converting markup like render array to string."),
 *   default = "true"
 * )
 */
class ComputedArrayToStringDataType extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return (string) $value;
  }

}
