<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a cent data type.
 *
 * @SearchApiDataType(
 *   id = "cent",
 *   label = @Translation("Euros to cents"),
 *   description = @Translation("Turns price values from euros to cents"),
 *   fallback_type = "integer",
 * )
 */
class Cent extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    return (int) ((float) $value * 100);
  }

}
