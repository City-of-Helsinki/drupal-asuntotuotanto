<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\CustomerOfferMessageResponse;
use Drupal\asu_api\Api\Request;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Customer offer message request.
 */
class CustomerOfferMessageRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/v1/profiles/me/offers/{offer_id}/offer_message/';
  protected const AUTHENTICATED = TRUE;

  /**
   * Constructor.
   *
   * @param \Drupal\user\UserInterface $sender
   *   Authenticated customer account.
   * @param int $offerId
   *   Offer identifier.
   */
  public function __construct(
    UserInterface $sender,
    private readonly int $offerId,
  ) {
    $this->setSender($sender);
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
  public static function getResponse(ResponseInterface $response): CustomerOfferMessageResponse {
    return CustomerOfferMessageResponse::createFromHttpResponse($response);
  }

}
