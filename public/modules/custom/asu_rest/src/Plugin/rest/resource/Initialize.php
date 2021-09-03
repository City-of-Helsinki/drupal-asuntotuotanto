<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\asu_application\Applications;
use Drupal\asu_rest\UserDto;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\image\Entity\ImageStyle;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
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
   * Return all data required by React to function properly.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The HTTP response object.
   */
  public function get() {
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
    $config = \Drupal::config('asu_rest.static_content')->get('static_content');
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
      'label' => 'Districts',
      'items' => $districts['hitas'],
      'suffix' => NULL,
    ];
    $responseData['project_district_haso'] = [
      'label' => 'Districts',
      'items' => $districts['haso'],
      'suffix' => NULL,
    ];

    $responseData['project_building_type'] = [
      'label' => $this->t('building_types'),
      'items' => $this->getBuildingTypes(),
      'suffix' => NULL,
    ];

    $responseData['project_new_development_status'] = [
      'label' => $this->t('New development status'),
      'items' => $this->getNewDevelopmentStatus(),
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
   * @return array|array[]
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getDistrictsByProjectOwnershipType() {
    $items = [
      'hitas' => [],
      'haso' => [],
    ];

    $projects = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['type' => 'project']);

    // Get all unique districts separately for both ownership types.
    foreach ($projects as $project) {
      if ($project->field_ownership_type->isEmpty() ||
          !$ownership = $project->field_ownership_type->first()->entity->getName()) {
        continue;
      }
      if ($project->field_district->isEmpty()) {
        continue;
      }

      // Take half_hitas into account.
      $ownership_type = $ownership == 'Haso' ? 'haso' : 'hitas';

      $district = $project->field_district->first()->entity;
      $name = $district->getName();
      if (!array_search($name, $items[strtolower($ownership_type)])) {
        $items[strtolower($ownership_type)][] = $name;
      }
    }
    return $items;
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
   * Return building types for react filters.
   *
   * @return array
   */
  protected function getBuildingTypes() {
    return [
      'block_of_flats',
      'row_house',
      'house',
    ];
  }

  /**
   * Return new development status types for react filters.
   *
   * @return array
   */
  protected function getNewDevelopmentStatus() {
    return [
      'under_planning',
      'under_construction',
      'pre_marketing',
      'ready_to_move',
    ];
  }

  /**
   * Return project state of sales for react filtes.
   *
   * @return array
   */
  protected function getProjectStatesOfSale() {
    return [
      'for_sale',
      'upcoming',
      'pre_marketing',
      'processing',
      'ready',
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
