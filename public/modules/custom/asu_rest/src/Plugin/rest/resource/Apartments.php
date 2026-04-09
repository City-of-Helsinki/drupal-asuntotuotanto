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

    $cid = $this->buildCacheKey('apartments', $params);
    if ($cachedResponse = $this->getCachedResponse($cid)) {
      return $cachedResponse;
    }

    ['offset' => $offset, 'limit' => $limit] = $this->getPaginationWithPage($request, 100, 250);

    $result = $this->searchService->searchApartments($params, NULL, $offset, $limit, FALSE);
    $this->searchMapper->primeProjectLookup($result['items']);
    $sources = array_map(
      fn (Node $apartment) => $this->searchMapper->mapApartmentListing($apartment),
      $result['items']
    );

    $payload = $this->searchMapper->buildSearchResponse($sources, $result['total'], 'apartment_listing');
    $this->setCachedPayload($cid, $payload);

    return $this->buildCacheableResponse($payload);
  }

}
