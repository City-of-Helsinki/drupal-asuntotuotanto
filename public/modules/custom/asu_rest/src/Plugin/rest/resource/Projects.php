<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\node\Entity\Node;
use Drupal\rest\ResourceResponse;
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
   * Responds to GET requests.
   */
  public function get(Request $request): ResourceResponse {
    $params = $request->query->all();
    $error = $this->validatePriceParams($params);
    if ($error instanceof ResourceResponse) {
      return $error;
    }

    $cid = $this->buildCacheKey('projects', $params);
    if ($cachedResponse = $this->getCachedResponse($cid)) {
      return $cachedResponse;
    }

    ['offset' => $offset, 'limit' => $limit] = $this->getPagination($request);
    $result = $this->searchService->searchProjects($params, $offset, $limit);
    $sources = array_map(
      fn (Node $project) => $this->searchMapper->mapProject($project),
      $result['items']
    );

    $payload = $this->searchMapper->buildSearchResponse($sources, $result['total'], 'project');
    $this->setCachedPayload($cid, $payload);

    return $this->buildResponse($sources, $result['total'], 'project');
  }

}
