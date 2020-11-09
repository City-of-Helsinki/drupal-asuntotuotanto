<?php
namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
  use StringTranslationTrait;

  /**
   * Constructor.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * Create.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
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

    // Taxonomy_id => elastic_index_id.
    $taxonomies_as_filters = [
      'building_types' => 'project_building_type',
      'districts' => 'project_district',
      'new_development_status' => 'project_new_development_status',
    ];

    $taxomy_machinenames_as_filters = [
      'states_of_sale' => 'project_state_of_sale',
    ];

    $vocabularies = Vocabulary::loadMultiple();
    $responseData = [];
    foreach ($taxonomies_as_filters as $taxonomy_name => $elastic_index_name) {
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadTree($taxonomy_name, 0, NULL, TRUE);

      if (!$terms) {
        continue;
      }

      $items = [];
      foreach ($terms as $term) {
        $items[] = $term->hasTranslation($currentLanguage->getId()) ?
          $term->getTranslation($currentLanguage->getId())->getName() : $term->getName();
      }

      $vocabulary_name = $vocabularies[$terms[0]->bundle()]->get('name');
      $index_data = [
        'label' => $vocabulary_name,
        'items' => $items,
        'suffix' => NULL,
      ];

      $responseData[$elastic_index_name] = $index_data;
    }

    foreach ($taxomy_machinenames_as_filters as $taxonomy_name => $elastic_index_name) {
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadTree($taxonomy_name, 0, NULL, TRUE);

      if (!$terms) {
        continue;
      }

      $items = [];
      foreach ($terms as $term) {
        $items[] = $term->field_machine_readable_name->value;
      }

      $vocabulary_name = $vocabularies[$terms[0]->bundle()]->get('name');
      $index_data = [
        'label' => $vocabulary_name,
        'items' => $items,
        'suffix' => NULL,
      ];

      $responseData[$elastic_index_name] = $index_data;
    }

    $responseData['properties'] = [
      'label' => $this->t('Additional selections'),
      'items' => $this->getProperties(),
      'suffix' => NULL,
    ];

    $responseData['room_count'] = [
      'label' => $this->t('Room count'),
      'items' => $this->getRoomCount(),
      'suffix' => $this->t('r'),
    ];

    $responseData['living_area'] = [
      'items' => [
        $this->t('At least'),
        $this->t('At most'),
      ],
      'label' => $this->t('area, m2'),
      'suffix' => 'm2',
    ];

    $responseData['sales_price'] = [
      'items' => [
        $this->t('Price at least'),
      ],
      'label' => $this->t('Price'),
      'suffix' => '€',
    ];

    return new JsonResponse($responseData);
  }

  /**
   * Get list of properties.
   *
   * @return array
   *   Array of apartment and room properties
   */
  protected function getProperties() {
    return [
      'project_has_elevator',
      'project_has_sauna',
      'has_apartment_sauna',
      'has_terrace',
      'has_balcony',
      'has_yard',
    ];
  }

  /**
   * Get list of room counts.
   *
   * @return array
   *   Array of room counts
   */
  protected function getRoomCount() {
    $count = array_map('strval', range(1, 4, 1));
    $count[] = "5+";
    return $count;
  }

}
