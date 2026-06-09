<?php

namespace Drupal\asu_elastic\Plugin\search_api\data_type;

use Drupal\node\Entity\Node;
use Drupal\search_api\DataType\DataTypePluginBase;

/**
 * Node id to url.
 *
 * @SearchApiDataType(
 *   id = "asu_url",
 *   label = @Translation("Id to url"),
 *   description = @Translation("Turns node id to url"),
 *   default = "true",
 *   fallback_type = "string",
 * )
 */
class IdToUrl extends DataTypePluginBase {

  /**
   * {@inheritdoc}
   */
  public function getValue($value) {
    if ($node = Node::load((int) $value)) {
      $baseUrl = getenv('ASU_ASUNTOTUOTANTO_URL');
      if ($baseUrl) {
        return rtrim($baseUrl, '/') . $node->toUrl()->toString();
      }
      $host = \Drupal::request()->getSchemeAndHttpHost();

      return $host . $node->toUrl()->toString();
    }
    return NULL;
  }

}
