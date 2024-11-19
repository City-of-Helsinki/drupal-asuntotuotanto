<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides an "any" data type.
 *
 * @SearchApiDataType(
 *   id = "any",
 *   label = @Translation("Any"),
 *   description = @Translation("Any field can be primarily used with computed fields."),
 *   default = "true",
 *   fallback_type = "string",
 * )
 */
class Any extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return $value;
  }

}
