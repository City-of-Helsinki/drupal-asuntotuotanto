<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a resource to get available filters.
 *
 * @RestResource(
 *   id = "filter_rest_resource",
 *   label = @Translation("Filters"),
 *   uri_paths = {
 *     "canonical" = "/filters",
 *     "https://www.drupal.org/link-relations/create" = "/filters"
 *   }
 * )
 */
final class Filters extends ResourceBase {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return parent::create($container, $configuration, $plugin_id, $plugin_definition);
  }

  /**
   * Responds to GET requests.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   */
  public function get(Request $request) {

    $currentLanguage = \Drupal::languageManager()->getCurrentLanguage();

    $taxonomies_as_filters = [
      'building_type' => 'project_building_type',
      'district' => 'project_district',
      'new_development_status' => 'project_new_development_status',
      'state_of_sale' => 'state_of_sale'
    ];

    $properties = [
      'project_has_elevator',
      'project_has_sauna',
      'has_apartment_sauna',
      'has_terrace',
      'has_balcony',
      'has_yard'
    ];

    #get taxonomies & terms

    $vocabularies = \Drupal\taxonomy\Entity\Vocabulary::loadMultiple();
    $data = [];
    foreach ($taxonomies_as_filters as $machine_name => $index_name) {

      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadTree($machine_name, 0, NULL, TRUE);

      $items = [];
      foreach ($terms as $term) {
        $vocabulary_name = $vocabularies[$term->bundle()]->get('name');
        $items[] = $term->hasTranslation($currentLanguage->getId()) ?
          $term->getTranslation($currentLanguage->getId())->getName() : $term->getName();
      }

      $index_data = [
        'label' => $vocabulary_name,
        'items' => $items,
      ];

      $data[$index_name] = $index_data;
    }

    $data['properties'] = [
      'label' => 'Lisävalinnat',
      'items' => $properties
    ];

    $data['room_count'] = [
      'label' => 'Huoneita',
      'items' => [
        '1 h',
        '2 h',
        '3 h',
        '4 h',
        '5+ h'
      ]
    ];

    $data['living_area'] = [
      'items' => [
        'Vähintään',
        'Enintään'
      ],
      'label' => 'Pinta-ala, m2'
    ];

    return new Response(json_encode($data));

  }

}
