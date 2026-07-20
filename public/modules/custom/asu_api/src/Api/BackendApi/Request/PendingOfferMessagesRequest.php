<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\PendingOfferMessagesResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Lists offers that need the initial offer email sent to customers.
 */
class PendingOfferMessagesRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/v1/sales/offers/pending_messages/';
  protected const AUTHENTICATED = TRUE;

  /**
   * Constructor.
   */
  public function __construct() {
    $this->sender = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): PendingOfferMessagesResponse {
    return PendingOfferMessagesResponse::createFromHttpResponse($response);
  }

}
