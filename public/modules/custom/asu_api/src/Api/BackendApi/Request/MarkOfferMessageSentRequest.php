<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\MarkOfferMessageSentResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Marks that the initial offer email was sent for an offer.
 */
class MarkOfferMessageSentRequest extends Request {

  protected const METHOD = 'POST';
  protected const PATH = '/v1/sales/offers/{offer_id}/mark_message_sent/';
  protected const AUTHENTICATED = TRUE;

  /**
   * Constructor.
   *
   * @param int $offerId
   *   Offer identifier.
   */
  public function __construct(private readonly int $offerId) {
    $this->sender = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(): string {
    return str_replace('{offer_id}', (string) $this->offerId, parent::getPath());
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
  public static function getResponse(ResponseInterface $response): MarkOfferMessageSentResponse {
    return MarkOfferMessageSentResponse::createFromHttpResponse($response);
  }

}
