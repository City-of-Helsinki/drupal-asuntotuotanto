<?php

namespace Drupal\asu_api\Api\BackendApi;

use Drupal\asu_api\Api\BackendApi\Service\ApplicationService;
use Drupal\asu_api\Api\BackendApi\Service\AuthenticationService;
use Drupal\asu_api\Api\BackendApi\Service\UserService;
use Drupal\asu_api\Api\RequestHandler;

/**
 * Integration to django.
 */
class BackendApi {

  /**
   * Authentication service.
   *
   * @var \Drupal\asu_api\Api\BackendApi\Service\AuthenticationService
   */
  private AuthenticationService $authenticationService;

  /**
   * Application service.
   *
   * @var \Drupal\asu_api\Api\BackendApi\Service\ApplicationService
   */
  private ApplicationService $applicationService;

  /**
   * User service.
   *
   * @var \Drupal\asu_api\Api\BackendApi\Service\UserService
   */
  private UserService $userService;

  /**
   * Constructor.
   */
  public function __construct(string $backendUrlVariable) {
    $url = getenv($backendUrlVariable);
    $requestHandler = new RequestHandler($url);
    $this->authenticationService = new AuthenticationService($requestHandler, \Drupal::request()->getSession());
    $this->applicationService = new ApplicationService($requestHandler);
    $this->userService = new UserService($requestHandler);
  }

  /**
   * Get authentication service.
   */
  public function getAuthenticationService(): AuthenticationService {
    return $this->authenticationService;
  }

  /**
   * Get application service.
   */
  public function getApplicationService(): ApplicationService {
    return $this->applicationService;
  }

  /**
   * Get user service.
   */
  public function getUserService(): UserService {
    return $this->userService;
  }

}
