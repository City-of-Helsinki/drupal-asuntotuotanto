<?php

namespace Drupal\asu_api\Api\BackendApi\Service;

use Drupal\asu_api\Api\BackendApi\Response\ApplicationResponse;
use Drupal\asu_api\Api\ServiceBase;
use Drupal\asu_api\Api\BackendApi\Request\CreateApplicationRequest;

/**
 * Handle requests and responses related to applications.
 */
class ApplicationService extends ServiceBase {

  /**
   * Send newly created application to backend.
   *
   * @param \Drupal\asu_api\BackendApi\Request\CreateApplicationRequest $request
   *   ApplicationRequest.
   * @param string $token
   *   Authentication token.
   *
   * @return \Drupal\asu_api\Api\BackendApi\Response\ApplicationResponse
   *   ApplicationResponse.
   *
   * @throws \Exception
   */
  public function sendApplication(CreateApplicationRequest $request, string $token): ApplicationResponse {
    $httpRequest = $this->requestHandler->buildAuthenticatedRequest($request, $token);
    $response = $this->requestHandler->send($httpRequest);
    return ApplicationResponse::createFromHttpResponse($response);
  }

}
