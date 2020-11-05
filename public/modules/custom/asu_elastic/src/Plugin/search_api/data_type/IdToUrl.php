<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\node\Entity\Node;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Provides a cent data type.
 *
 * @SearchApiDataType(
 *   id = "asu_url",
 *   label = @Translation("Id to url"),
 *   description = @Translation("Turns node id to url"),
 *   fallback_type = "string",
 * )
 */
class IdToUrl extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if ($node = Node::load((int) $value)) {
      $host = \Drupal::request()->getHost();
      return $host . $node->toUrl()->toString();
    }
    return NULL;
  }

}
