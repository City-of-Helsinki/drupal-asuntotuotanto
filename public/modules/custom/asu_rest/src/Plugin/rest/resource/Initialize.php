<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\asu_application\Applications;
use Drupal\asu_rest\UserDto;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides a resource to get user applications.
 *
 * @RestResource(
 *   id = "asu_initialize",
 *   label = @Translation("Initialize"),
 *   uri_paths = {
 *     "canonical" = "/initialize",
 *     "https://www.drupal.org/link-relations/create" = "/initialize"
 *   }
 * )
 */
final class Initialize extends ResourceBase {

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
    $response = [];

    $response['filters'] = $this->getFilters();
    $response['static_content'] = $this->getStaticContent();
    $response['apartment_application_status'] = $this->getApartmentApplicationStatus();
    $response['token'] = \Drupal::service('csrf_token')->get(CsrfRequestHeaderAccessCheck::TOKEN_KEY);

    $response['user'] = [
      'user_id' => 0,
      'email_address' => '',
      'username' => '',
      'applications' => [],
    ];

    /** @var \Drupal\user\Entity\User $user */
    if (\Drupal::currentUser()->isAuthenticated()) {
      $user = User::load(\Drupal::currentUser()->id());
      $response['user'] = $this->getUser($user);
      $response['user']['applications'] = $this->getUserApplications($user);
    }

    $headers = getenv('APP_ENV') == 'test' ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];

    return new ModifiedResourceResponse($response, 200, $headers);
  }

  /**
   * Get user data for logged in user.
   *
   * @param \Drupal\user\Entity\User $user
   *   User object.
   *
   * @return array
   *   Array of user data.
   */
  private function getUser(User $user): array {
    $userData = UserDto::createFromUser($user);
    return $userData->toArray();
  }

  /**
   * Get application apartments sent by the user.
   *
   * @param \Drupal\user\Entity\User $user
   *   User object.
   *
   * @return array
   *   Array of applications by user.
   */
  private function getUserApplications(User $user) {
    return Applications::applicationsByUser($user->id())
      ->getApartmentApplicationsByProject();
  }

  /**
   * Get application count as enum for apartments.
   *
   * @return array
   *   Array of application statuses by apartment.
   */
  private function getApartmentApplicationStatus(): array {
    return Applications::create()
      ->getApartmentApplicationStatuses();

  }

  /**
   * Get the static content.
   */
  private function getStaticContent(): array {
    $config = \Drupal::config('asu_rest.static_content');
    return $config->get('static_content');
  }

  /**
   * Get the filters.
   */
  private function getFilters(): array {
    $languageCode = \Drupal::languageManager()
      ->getCurrentLanguage()
      ->getId();

    // $cacheKey = $languageCode . '_asu_filters';
    // if(!$cached = \Drupal::cache()->get($languageCode .'_asu_filters')) {.
    try {
      $content = $this->doGetFilters();

      // District items is associative array for some reason.
      if (isset($content['project_district_hitas']['items'])) {
        $content['project_district_hitas']['items'] = array_values($content['project_district_hitas']['items']);
      }

      // District items is associative array for some reason.
      if (isset($content['project_district_haso']['items'])) {
        $content['project_district_haso']['items'] = array_values($content['project_district_haso']['items']);
      }
      // \Drupal::cache()->set($cacheKey, $content);
      return $content;
    }
    catch (\Exception $e) {
      \Drupal::logger('asu_filters')->critical('Unable to fetch filter for react component: ' . $e->getMessage());
      return [];
    }
    // }
  }

  /**
   *
   */
  protected function doGetFilters() {
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

        $items = [
          'Hitas' => [],
          'Haso' => [],
        ];

        // Get all unique districts separately for both ownership types.
        foreach ($projects as $project) {
          if (!$project->field_ownership_type->first()) {
            continue;
          }
          if (!$ownership = $project->field_ownership_type->first()->entity->getName()) {
            continue;
          }
          $ownership = $ownership == 'Haso' ? 'Haso' : 'Hitas';
          $district = $project->field_district->first()->entity;

          $name = $district->hasTranslation($currentLanguage->getId()) ?
            $district->getTranslation($currentLanguage->getId())->getName() : $district->getName();
          if (!array_search($name, $items[$ownership])) {
            $items[strtolower('project_district_' . $ownership)][] = $name;
          }
        }

        $vocabulary_name = $vocabularies[$terms[0]->bundle()]->get('name');
        $index_hitas = [
          'label' => $vocabulary_name,
          'items' => isset($items['project_district_hitas']) ? array_unique($items['project_district_hitas']) : [],
          'suffix' => NULL,
        ];
        $responseData[strtolower('project_district_hitas')] = $index_hitas;

        $vocabulary_name = $vocabularies[$terms[0]->bundle()]->get('name');
        $index_haso = [
          'label' => $vocabulary_name,
          'items' => isset($items['project_district_haso']) ? array_unique($items['project_district_haso']) : [],
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

    $responseData['debt_free_sales_price'] = [
      'items' => [
        $this->t('Price at most'),
      ],
      'label' => $this->t('Price at most'),
      'suffix' => '€',
    ];

    return $responseData;
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
