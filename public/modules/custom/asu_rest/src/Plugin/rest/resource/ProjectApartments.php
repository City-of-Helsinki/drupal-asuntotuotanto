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
 *     "canonical" = "/projects/{uuid}/apartments"
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
   *
   * @param string $uuid
   *   Project UUID (or node ID for backward compatibility) from the URL.
   */
  public function get(string $uuid): ResourceResponse {
    $request = \Drupal::request();
    $params = $request->query->all();
    $error = $this->validatePriceParams($params);
    if ($error instanceof ResourceResponse) {
      return $error;
    }

    $project = $this->searchService->loadProject($uuid);
    if (!$project) {
      return new ResourceResponse(['message' => 'Project not found.'], 404, $this->getTestingHeaders());
    }

    $cid = $this->buildCacheKey('project_apartments:' . $uuid, $params);
    if ($cachedResponse = $this->getCachedResponse($cid)) {
      return $cachedResponse;
    }

    ['offset' => $offset, 'limit' => $limit] = $this->getPaginationWithPage($request, 100, 250);

    $result = $this->searchService->searchApartments($params, (int) $project->id(), $offset, $limit);
    $this->searchMapper->primeProjectLookupWithKnownProject($result['items'], $project);
    $sources = array_map(
      fn (Node $apartment) => $this->searchMapper->mapApartmentListing($apartment),
      $result['items']
    );

    $payload = $this->searchMapper->buildSearchResponse($sources, $result['total'], 'apartment_listing');
    $this->setCachedPayload($cid, $payload);

    return $this->buildCacheableResponse($payload);
  }

}
