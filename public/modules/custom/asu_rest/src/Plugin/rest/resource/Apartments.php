<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an Elasticsearch-style apartments list.
 *
 * @RestResource(
 *   id = "asu_apartments",
 *   label = @Translation("Apartments (ES compatible)"),
 *   uri_paths = {
 *     "canonical" = "/apartments"
 *   }
 * )
 */
final class Apartments extends AsuSearchResourceBase {

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
    $error = $this->validatePriceParams($params);
    if ($error instanceof ResourceResponse) {
      return $error;
    }

    $limit = (int) $request->query->get('size', 100);
    if ($limit <= 0) {
      $limit = 100;
    }
    // Keep response time stable for internal client polling.
    $limit = min($limit, 250);

    $offset = max(0, (int) $request->query->get('from', 0));
    if (!$request->query->has('from') && $request->query->has('page')) {
      $page = max(1, (int) $request->query->get('page', 1));
      $offset = ($page - 1) * $limit;
    }

    $result = $this->searchService->searchApartments($params, NULL, $offset, $limit, FALSE);
    $this->searchMapper->primeProjectLookup($result['items']);
    $sources = array_map(
      fn (Node $apartment) => $this->searchMapper->mapApartmentListing($apartment),
      $result['items']
    );

    return $this->buildResponse($sources, $result['total'], 'apartment_listing');
  }

}
