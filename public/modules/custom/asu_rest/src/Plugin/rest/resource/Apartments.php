<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;

/**
 * Provides a resource to subscribe to mailing list.
 *
 * @RestResource(
 *   id = "asu_content",
 *   label = @Translation("Content"),
 *   uri_paths = {
 *     "canonical" = "/content/{id}",
 *     "https://www.drupal.org/link-relations/create" = "/content/{id}"
 *   }
 * )
 */
final class Apartments extends ResourceBase
{

  /**
   * Responds to GET requests.
   *
   * @param string $id
   *   Data required by the endpoint.
   *
   * @return Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function get(string $id): ModifiedResourceResponse
  {
    if (!$node = Node::load($id)) {
      return new ModifiedResourceResponse([], 404);
    }

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
    $storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $storage->load($id);
    $build = $view_builder->view($node, 'full');

    $output = render($build);
    return new ModifiedResourceResponse($output);
  }

}
