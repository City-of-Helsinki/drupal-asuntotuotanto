<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\GetApartmentStatusResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to create new backend user.
 */
class GetApartmentStatusRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/v1/sales/apartment_states/';
  protected const AUTHENTICATED = TRUE;

  /**
   * {@inheritDoc}
   */
  public function __construct() {
    $this->sender = NULL;
  }

  /**
   * Data to array.
   */
  public function toArray(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): GetApartmentStatusResponse {
    return GetApartmentStatusResponse::createFromHttpResponse($response);
  }

}
