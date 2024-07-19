<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\GetApartmentRevaluationsResponse;
use Drupal\asu_api\Api\Request;
use Drupal\Core\Datetime\DrupalDateTime;
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

  public function getPath(array $queryparameters = []): string {
//    [$start, $end] = $queryparameters;
    $start = DrupalDateTime::createFromTimestamp(strtotime('-2 year'));
    $end = new DrupalDateTime();
    return sprintf(
      "%s%s%s%s%s",
      parent::getPath(),
      '?start_time=',
      $start->format("Y-m-d\TH:i:s.u\Z"),
      '&end_time=',
      $end->format("Y-m-d\TH:i:s.u\Z"),
    );
  }

}
