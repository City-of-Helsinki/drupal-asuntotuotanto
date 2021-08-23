<?php

namespace Drupal\asu_api\Api;

/**
 * Base class for services.
 */
abstract class ServiceBase {
  /**
   * Request handler.
   *
   * @var Drupal\asu_api\BackendApi\RequestHandler
   */
  protected $requestHandler;

  /**
   * Constructor.
   */
  public function __construct(RequestHandler $requestHandler) {
    $this->requestHandler = $requestHandler;
  }

}
