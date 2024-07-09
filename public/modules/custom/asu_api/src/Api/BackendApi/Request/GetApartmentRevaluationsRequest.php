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
  protected const PATH = '/v1/sales/apartment/revaluations/summary/{url_params}';
  protected const AUTHENTICATED = TRUE;

  /**
   * {@inheritDoc}
   */
  public function __construct() {
    $this->sender = NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function getPath(): string {
    $dt = new \DateTime();
    $end_date = $dt->format('Y-m-d\TH:i:s');
    $dt->modify('-30 min');
    $start_date = $dt->format('Y-m-d\TH:i:s');
    $url_params = '?start_time=' . $start_date . '&end_time=' . $end_date;

    return str_replace('/{url_params}', $url_params, parent::getPath());
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
