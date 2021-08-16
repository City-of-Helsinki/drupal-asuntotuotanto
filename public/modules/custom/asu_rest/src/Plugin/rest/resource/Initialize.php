<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\asu_application\Applications;
use Drupal\asu_rest\UserDto;
use Drupal\asu_api\Api\DrupalApi\Request\FilterRequest;
use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
      'applications' => []
    ];

    /** @var \Drupal\user\Entity\User $user */
    if (\Drupal::currentUser()->isAuthenticated()) {
      $user = User::load(\Drupal::currentUser()->id());
      $response['user'] = $this->getUser($user);
      $response['user']['applications'] = $this->getUserApplications($user);
    }

    return new ModifiedResourceResponse($response, 200);
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
      /** @var \Drupal\asu_api\Api\DrupalApi\DrupalApi $drupalApi */
      $drupalApi = \Drupal::service('asu_api.drupalapi');

      $content = $drupalApi
        ->getFiltersService()
        ->getFilters(FilterRequest::create($languageCode))
        ->getContent();

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

}
