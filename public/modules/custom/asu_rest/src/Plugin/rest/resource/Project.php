<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Elasticsearch-style project detail endpoint.
 *
 * @RestResource(
 *   id = "asu_project",
 *   label = @Translation("Project (ES compatible)"),
 *   uri_paths = {
 *     "canonical" = "/projects/{project_id}"
 *   }
 * )
 */
final class Project extends AsuSearchResourceBase {

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
    $project = $this->searchService->loadProject($project_id);
    if (!$project) {
      return new ResourceResponse(['message' => 'Project not found.'], 404, $this->getTestingHeaders());
    }

    $source = $this->searchMapper->mapProject($project);
    return $this->buildResponse([$source], 1, 'project');
  }

}
