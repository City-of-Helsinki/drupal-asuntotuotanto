<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\OfferActionResponse;
use Drupal\asu_api\Api\Request;
use Drupal\user\UserInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Customer accept/reject offer request.
 */
class OfferActionRequest extends Request {

  protected const METHOD = 'PATCH';
  protected const PATH = '/v1/profiles/me/offers/{offer_id}/';
  protected const AUTHENTICATED = TRUE;

  /**
   * Constructor.
   *
   * @param \Drupal\user\UserInterface $sender
   *   Authenticated customer account.
   * @param int $offerId
   *   Offer identifier.
   * @param string $state
   *   Accepted or rejected.
   */
  public function __construct(
    UserInterface $sender,
    private readonly int $offerId,
    private readonly string $state,
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
    return [
      'state' => $this->state,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function getResponse(ResponseInterface $response): OfferActionResponse {
    return OfferActionResponse::createFromHttpResponse($response);
  }

}
