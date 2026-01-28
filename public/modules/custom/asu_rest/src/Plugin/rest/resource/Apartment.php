<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Elasticsearch-style apartment detail endpoint.
 *
 * @RestResource(
 *   id = "asu_apartment",
 *   label = @Translation("Apartment (ES compatible)"),
 *   uri_paths = {
 *     "canonical" = "/apartments/{apartment_id}"
 *   }
 * )
 */
final class Apartment extends AsuSearchResourceBase {

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
  public function get(int $apartment_id): ResourceResponse {
    $apartment = $this->searchService->loadApartment($apartment_id);
    if (!$apartment) {
      return new ResourceResponse(['message' => 'Apartment not found.'], 404, $this->getTestingHeaders());
    }

    $source = $this->searchMapper->mapApartmentDetail($apartment);
    return $this->buildResponse([$source], 1, 'apartment');
  }

}
