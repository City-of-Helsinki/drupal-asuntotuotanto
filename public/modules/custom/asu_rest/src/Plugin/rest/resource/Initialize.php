<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\asu_application\Applications;
use Drupal\asu_rest\UserDto;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\User;

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
   * Return all data required by React to function properly.
   */
  public function get() {
    $response = [];

    $filters = [];
    if ($cache = \Drupal::cache()
      ->get('asu_initialize_filters')) {
      $filters = $cache->data;
    }
    else {
      $filters = $this->getFilters();
      \Drupal::cache()
        ->set('asu_initialize_filters', $filters, (time() + 60 * 60));
    }
    $response['filters'] = $filters;

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
      // @todo Followed projects.
      $response['user']['followed_projects'][] = 15;
    }

    $headers = getenv('APP_ENV') == 'testing' ? [
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
    // @todo Followed projects.
    $uid = \Drupal::currentUser()->id();
    $contents = \Drupal::config('asu_rest.static_content')->get('static_content');
    $urls = \Drupal::config('asu_rest.static_content')->get('static_urls');

    $config = [];
    foreach ($contents as $key => $content) {
      // phpcs:ignore
      $config[$key] = (string) t($content);
    }

    $langCode = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $urls = array_map(function ($string) use ($langCode) {
      return str_replace('@lang', $langCode, $string);
    }, $urls);

    $config = array_merge($config, $urls);

    $config['followed_projects_page_url'] = "/user/$uid/followed_projects";
    return $config;
  }

  /**
   * Get all values used in React filter bar.
   */
  protected function getFilters() {
    $responseData = [];

    $districts = $this->getDistrictsByProjectOwnershipType();
    $responseData['project_district_hitas'] = [
      'label' => (string)$this->t('Districts'),
      'items' => $districts['hitas'],
      'suffix' => NULL,
    ];
    $responseData['project_district_haso'] = [
      'label' => (string)$this->t('Districts'),
      'items' => $districts['haso'],
      'suffix' => NULL,
    ];

    $responseData['project_building_type'] = [
      'label' => $this->t('building_types'),
      'items' => $this->getBuildingTypes(),
      'suffix' => NULL,
    ];

    $responseData['project_state_of_sale'] = [
      'label' => $this->t('State of sale'),
      'items' => $this->getProjectStatesOfSale(),
      'suffix' => NULL,
    ];

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

    return $responseData;
  }

  /**
   * Get districts sorted by project ownership type.
   *
   * @return array|array[]
   *   Array of districts sorted by project ownership.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getDistrictsByProjectOwnershipType() {
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

  /**
   * Get list of properties.
   *
   * @return array
   *   Array of apartment and room properties
   */
  protected function getProperties() {
    return [
      'PROJECT_HAS_ELEVATOR',
      'PROJECT_HAS_SAUNA',
      'HAS_APARTMENT_SAUNA',
      'HAS_TERRACE',
      'HAS_BALCONY',
      'HAS_YARD',
    ];
  }

  /**
   * Return building types for react filters.
   *
   * @return array
   *   Array of building types as enums.
   */
  protected function getBuildingTypes() {
    return [
      'BLOCK_OF_FLATS',
      'ROW_HOUSE',
      'HOUSE',
    ];
  }

  /**
   * Return project state of sales for react filtes.
   *
   * @return array
   *   Array of project state of sale values as enums.
   */
  protected function getProjectStatesOfSale() {
    return [
      'FOR_SALE',
      'PRE_MARKETING',
      'READY',
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
