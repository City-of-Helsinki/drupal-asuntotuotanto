<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an Elasticsearch-style projects list.
 *
 * @RestResource(
 *   id = "asu_projects",
 *   label = @Translation("Projects (ES compatible)"),
 *   uri_paths = {
 *     "canonical" = "/projects"
 *   }
 * )
 */
final class Projects extends AsuSearchResourceBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('asu_rest'),
      $container->get('asu_rest.search_service'),
      $container->get('asu_rest.search_mapper'),
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get(Request $request): ResourceResponse {
    $params = $request->query->all();
    if ($error = $this->validatePriceParams($params)) {
      return $error;
    }

    ['offset' => $offset, 'limit' => $limit] = $this->getPagination($request);
    $result = $this->searchService->searchProjects($params, $offset, $limit);
    $sources = array_map(
      fn (Node $project) => $this->searchMapper->mapProject($project),
      $result['items']
    );

    return $this->buildResponse($sources, $result['total'], 'project');
  }

}
