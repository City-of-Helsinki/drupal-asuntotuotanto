<?php

namespace Drupal\asu_api\Api\DrupalApi\Service;

use Drupal\asu_api\Api\DrupalApi\Request\ApartmentRequest;
use Drupal\asu_api\Api\DrupalApi\Response\ApartmentResponse;
use Drupal\asu_api\Api\RequestHandler;

/**
 * Application service.
 */
class ApartmentService {
  /**
   * Request handler.
   *
   * @var \Drupal\asu_api\Api\RequestHandler
   */
  private RequestHandler $requestHandler;

  /**
   * Constructor.
   *
   * @param \Drupal\asu_api\Api\RequestHandler $requestHandler
   */
  public function __construct(RequestHandler $requestHandler) {
    $this->requestHandler = $requestHandler;
  }

  /**
   * Get content for certain apartment or project.
   *
   * @param \Drupal\asu_api\Api\DrupalApi\Request\ApartmentRequest $request
   *   ApplicationRequest.
   *
   * @return \Drupal\asu_api\Api\BackendApi\Response\ApartmentResponse
   *   ApartmentResponse.
   *
   * @throws \Exception
   */
  public function getContent(ApartmentRequest $request): ApartmentResponse {
    $httpRequest = $this->requestHandler->buildRequest($request);
    $response = $this->requestHandler->send($httpRequest);
    return ApartmentResponse::createFromHttpResponse($response);
  }

}
