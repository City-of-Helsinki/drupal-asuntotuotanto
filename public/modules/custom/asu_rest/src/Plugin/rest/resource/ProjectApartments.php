<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Elasticsearch-style apartments list for a project.
 *
 * @RestResource(
 *   id = "asu_project_apartments",
 *   label = @Translation("Project apartments (ES compatible)"),
 *   uri_paths = {
 *     "canonical" = "/projects/{project_id}/apartments"
 *   }
 * )
 */
final class ProjectApartments extends AsuSearchResourceBase {

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
  public function get(int $project_id): ResourceResponse {
    $request = \Drupal::request();
    $params = $request->query->all();
    if ($error = $this->validatePriceParams($params)) {
      return $error;
    }

    ['offset' => $offset, 'limit' => $limit] = $this->getPagination($request);
    $result = $this->searchService->searchApartments($params, $project_id, $offset, $limit);
    $sources = array_map(
      fn (Node $apartment) => $this->searchMapper->mapApartmentListing($apartment),
      $result['items']
    );

    return $this->buildResponse($sources, $result['total'], 'apartment_listing');
  }

}
