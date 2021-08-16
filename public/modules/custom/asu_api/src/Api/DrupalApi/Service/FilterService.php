<?php

namespace Drupal\asu_api\Api\DrupalApi\Service;

use Drupal\asu_api\Api\DrupalApi\Request\FilterRequest;
use Drupal\asu_api\Api\DrupalApi\Response\FilterResponse;
use Drupal\asu_api\Api\RequestHandler;

/**
 * Handles endpoints for filters.
 */
class FilterService {
  /**
   * Request handler.
   *
   * @var \Drupal\asu_api\Api\RequestHandler
   */
  private RequestHandler $requestHandler;

  /**
   * Constructor.
   */
  public function __construct(RequestHandler $requestHandler) {
    $this->requestHandler = $requestHandler;
  }

  /**
   * Get filters.
   *
   * @param \Drupal\asu_api\Api\DrupalApi\Request\FilterRequest $filterRequest
   *   Request.
   *
   * @return \Drupal\asu_api\Api\DrupalApi\Response\FilterResponse
   *   Response.
   *
   * @throws \Drupal\asu_api\Exception\ApplicationRequestException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getFilters(FilterRequest $filterRequest): FilterResponse {
    $httpRequest = $this->requestHandler->buildRequest($filterRequest);
    $response = $this->requestHandler->send($httpRequest);
    return FilterResponse::createFromHttpResponse($response);
  }

}
