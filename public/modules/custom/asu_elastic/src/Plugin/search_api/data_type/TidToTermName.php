<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Parse string to number.
 *
 * @SearchApiDataType(
 *   id = "asu_tidtotermname",
 *   label = @Translation("Term id to name"),
 *   description = @Translation("Convert term id to term label"),
 *   default = "true",
 *   fallback_type = "string",
 * )
 */
class TidToTermName extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($tid) {
    /** @var \Drupal\taxonomy\Entity\Term $term */
    $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tid);
    return $term->label();
  }

}
