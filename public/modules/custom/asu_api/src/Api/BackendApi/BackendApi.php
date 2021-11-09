<?php

namespace Drupal\asu_api\Api\BackendApi;

use Drupal\asu_api\Api\BackendApi\Service\ApplicationService;
use Drupal\asu_api\Api\BackendApi\Service\AuthenticationService;
use Drupal\asu_api\Api\BackendApi\Service\UserService;
use Drupal\asu_api\Api\ClientFactory;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\RequestHandler;
use Drupal\asu_api\Api\Response;
use http\Client;
use Psr\Http\Message\RequestInterface;

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

  private \GuzzleHttp\Client $client;

  /**
   * Constructor.
   */
  public function __construct(ClientFactory $clientFactory, AuthenticationService $auth) {
    $this->clientFactory = $clientFactory;
    $this->authenticationService = $auth;
  }

  public function send(Request $request, array $options = []): Response {
    if ($request->requiresAuthentication()) {
      if($token = $this->authenticationService->handleAuthentication($request->getUser())) {
        $options['headers']['Authorization'] = sprintf("Bearer %s", $token);
      }
    }
    $client = $this->clientFactory->createClient($options);
    $response = $client->send(ClientFactory::createRequest($request), $options['headers']);
    return $request::getResponse($response);
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
