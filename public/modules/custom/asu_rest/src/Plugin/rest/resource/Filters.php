<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\search_api\Entity\Index;
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

        // Get all unique districts separately for both ownership types.
        $activeProjectDistricts = $this->getActiveProjectDistricts();

        $vocabulary_name = $vocabularies[$terms[0]->bundle()]->get('name');
        $index_hitas = [
          'label' => $vocabulary_name,
          'items' => $activeProjectDistricts['hitas'],
          'suffix' => NULL,
        ];
        $responseData[strtolower('project_district_hitas')] = $index_hitas;

        $vocabulary_name = $vocabularies[$terms[0]->bundle()]->get('name');
        $index_haso = [
          'label' => t('Districts'),
          'items' => $activeProjectDistricts['haso'],
          'suffix' => NULL,
        ];

        $responseData[strtolower('project_district_haso')] = $index_haso;

      }
      else {
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

    }

    foreach ($filters['taxonomy_machinename'] as $taxonomy_name => $elastic_index_name) {
      /** @var \Drupal\config_terms\TermStorageInterface $term_storage */
      $term_storage = \Drupal::entityTypeManager()
        ->getStorage('config_terms_term');
      $terms = $term_storage->loadTree($taxonomy_name);

      if (!$terms) {
        continue;
      }

      $items = [];
      foreach ($terms as $term) {
        $items[] = $term->id();
      }

      $index_data = [
        'label' => $this->t('State of sale'),
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
      'suffix' => $this->t('rooms'),
    ];

    $responseData['living_area'] = [
      'items' => [
        $this->t('At least'),
        $this->t('At most'),
      ],
      'label' => $this->t('area, m2'),
      'suffix' => 'm2',
    ];

    $responseData['price'] = [
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
    $count = array_map('strval', range(1, 4));
    $count[] = "5+";
    return $count;
  }

  /**
   * Get districts which have active projects.
   *
   * @return array
   *   districts separately for haso and hitas
   */
  private function getActiveProjectDistricts(): array {
    $indexes = Index::loadMultiple();
    $index = isset($indexes['apartment']) ? $indexes['apartment'] : reset($indexes);
    $query = $index->query();
    $query->range(0, 10000);
    $query->addCondition('_language', ['fi'], 'IN');
    $query->addCondition('project_state_of_sale', ['upcoming'], 'NOT IN');
    $query->addCondition('project_ownership_type', ['hitas', 'haso'], 'IN');
    $resultItems = $query->execute()->getResultItems();

    $projects = [
      'hitas' => [],
      'haso' => [],
    ];

    foreach ($resultItems as $resultItem) {
      if (isset($resultItem->getField('project_ownership_type')->getValues()[0])) {
        $district = isset($resultItem->getField('project_district')->getValues()[0]) ? $resultItem->getField('project_district')->getValues()[0] : '';
        if ($district) {
          $projects[strtolower($resultItem->getField('project_ownership_type')->getValues()[0])][] = $district;
        }
      }
    }

    foreach ($projects as $key => $project) {
      $filtered = array_unique($project);
      asort($filtered);
      $projects[$key] = $filtered;
    }

    return [
      'hitas' => array_values($projects['hitas']),
      'haso' => array_values($projects['haso']),
    ];
  }

}
