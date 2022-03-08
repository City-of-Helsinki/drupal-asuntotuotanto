<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\node\Entity\Node;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Parse string to number.
 *
 * @SearchApiDataType(
 *   id = "asu_number",
 *   label = @Translation("string to numeric value"),
 *   description = @Translation("Parse number from string"),
 *   fallback_type = "string",
 * )
 */
class StringToNumber extends DataTypePluginBase {
  /**
   * {@inheritdoc}
   */
  public function getValue($string) {
    return ($string && is_string($string)) ? (int) filter_var($string, FILTER_SANITIZE_NUMBER_INT) : 0;
  }
}
