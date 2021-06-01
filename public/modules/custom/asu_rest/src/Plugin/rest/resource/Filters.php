<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\rest\Plugin\ResourceBase;
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
    $config = \Drupal::config('asu_rest.filters');
    $filters = $config->get('filters');

    $vocabularies = Vocabulary::loadMultiple();
    $responseData = [];

    foreach ($filters['taxonomy'] as $taxonomy_name => $elastic_index_name) {
      $terms = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadTree($taxonomy_name, 0, NULL, TRUE);

      if (!$terms) {
        continue;
      }

      $items = [];

      if ($taxonomy_name == 'districts') {

        $projects = \Drupal::entityTypeManager()
          ->getStorage('node')
          ->loadByProperties(['type' => 'project']);

        $items = [];
        // Get all unique districts separately for both ownership types.
        foreach ($projects as $project){
          if(!$ownership = $project->field_ownership_type->first()->entity->getName()){
            continue;
          }
          $district = $project->field_district->first()->entity;

          $name = $district->hasTranslation($currentLanguage->getId()) ?
            $district->getTranslation($currentLanguage->getId())->getName() : $district->getName();
          if(!array_search($name, $items[$ownership])){
            $items[$ownership][] = $name;
          }
        }
      }
      else {
        foreach ($terms as $term) {
          $items[] = $term->hasTranslation($currentLanguage->getId()) ?
            $term->getTranslation($currentLanguage->getId())->getName() : $term->getName();
        }
      }

      $vocabulary_name = $vocabularies[$terms[0]->bundle()]->get('name');
      $index_data = [
        'label' => $vocabulary_name,
        'items' => $items,
        'suffix' => NULL,
      ];

      $responseData[$elastic_index_name] = $index_data;
    }

    foreach ($filters['taxonomy_machinename'] as $taxonomy_name => $elastic_index_name) {
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

    $responseData['debt_free_sales_price'] = [
      'items' => [
        $this->t('Price at most'),
      ],
      'label' => $this->t('Price at most'),
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
