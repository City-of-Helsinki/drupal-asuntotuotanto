<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Psr\Http\Message\ResponseInterface;
use Drupal\asu_api\Api\BackendApi\Response\GetApartmentStatusResponse;
use Drupal\asu_api\Api\Request;

/**
 * A request to create new backend user.
 */
class GetApartmentStatusRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/v1/sales/sold_apartments/';
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
