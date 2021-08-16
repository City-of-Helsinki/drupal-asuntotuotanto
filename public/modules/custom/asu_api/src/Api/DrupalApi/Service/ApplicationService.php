<?php

namespace Drupal\asu_api\Api\DrupalApi\Service;

use Drupal\asu_api\Api\RequestHandler;

/**
 * Application service.
 */
class ApplicationService {
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

}
