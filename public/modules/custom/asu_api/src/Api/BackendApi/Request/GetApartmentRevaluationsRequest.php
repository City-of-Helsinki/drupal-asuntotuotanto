<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\GetApartmentRevaluationsResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * A request to get apartment revaluations.
 */
class GetApartmentRevaluationsRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/v1/sales/apartment/revaluations/summary';
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
  public static function getResponse(ResponseInterface $response): GetApartmentRevaluationsResponse {
    return GetApartmentRevaluationsResponse::createFromHttpResponse($response);
  }

}
