<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\BackendApi\Response\PendingOfferRemindersResponse;
use Drupal\asu_api\Api\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Lists offers that need a reminder email before the deadline.
 */
class PendingOfferRemindersRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/v1/sales/offers/pending_reminders/';
  protected const AUTHENTICATED = TRUE;

  /**
   * Constructor.
   *
   * @param int|null $daysBefore
   *   Days before deadline to include offers.
   */
  public function __construct(private readonly ?int $daysBefore = NULL) {
    $this->sender = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath(): string {
    if ($this->daysBefore === NULL) {
      return parent::getPath();
    }
    return parent::getPath() . '?days_before=' . $this->daysBefore;
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
  public static function getResponse(ResponseInterface $response): PendingOfferRemindersResponse {
    return PendingOfferRemindersResponse::createFromHttpResponse($response);
  }

}
