<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Parse string to number.
 *
 * @SearchApiDataType(
 *   id = "asu_strtolower",
 *   label = @Translation("Lowercase string"),
 *   description = @Translation("Strtolower string"),
 *   default = "true",
 *   fallback_type = "string",
 * )
 */
class LowercaseString extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($string) {
    return strtolower($string);
  }

}
