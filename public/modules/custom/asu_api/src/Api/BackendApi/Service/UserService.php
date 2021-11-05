<?php

namespace Drupal\asu_api\Api\BackendApi\Service;

use Drupal\asu_api\Api\BackendApi\Request\CreateUserRequest;
use Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse;
use Drupal\asu_api\Api\BackendApi\Response\UpdateUserResponse;
use Drupal\asu_api\Api\BackendApi\Response\UserResponse;
use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Api\ServiceBase;

/**
 * Handle requests and responses related to applications.
 */
class UserService extends ServiceBase {

  /**
   * Send newly created user to backend.
   *
   * @param \Drupal\asu_api\BackendApi\Request\CreateUserRequest $request
   *   CreateUserRequest.
   *
   * @return \Drupal\asu_api\Api\BackendApi\Response\CreateUserResponse
   *   CreateUserResponse.
   *
   * @throws \Exception
   */
  public function createUser(CreateUserRequest $request): CreateUserResponse {
    $httpRequest = $this->requestHandler->buildRequest($request);
    $response = $this->requestHandler->send($httpRequest);
    return CreateUserResponse::createFromHttpResponse($response);
  }

  /**
   * Get user information from backend.
   *
   * @param \Drupal\asu_api\Api\Request $request
   *   Request.
   * @param string $token
   *   Authentication token.
   *
   * @return \Drupal\asu_api\Api\Response
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getUser(Request $request, string $token): Response {
    $httpRequest = $this->requestHandler->buildAuthenticatedRequest($request, $token);
    $response = $this->requestHandler->send($httpRequest);
    return UserResponse::createFromHttpResponse($response);
  }

  /**
   * Send updated user data to backend.
   *
   * @param \Drupal\asu_api\Api\Request $request
   *   Request.
   * @param string $token
   *   Authentication token.
   *
   * @return \Drupal\asu_api\Api\Response
   *   Response.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function updateUser(Request $request, $token): UpdateUserResponse {
    $httpRequest = $this->requestHandler->buildAuthenticatedRequest($request, $token);
    $response = $this->requestHandler->send($httpRequest);
    return UpdateUserResponse::createFromHttpResponse($response);
  }

}
