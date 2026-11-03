<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\ApplicationRequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for customer offer accept/reject.
 */
class OfferActionResponse extends Response {

  /**
   * Offer data from backend.
   *
   * @var array
   */
  private array $offer;

  /**
   * Constructor.
   */
  public function __construct(array $offer) {
    $this->offer = $offer;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): array {
    return $this->offer;
  }

  /**
   * {@inheritdoc}
   */
  public static function createFromHttpResponse(ResponseInterface $response): self {
    if (!self::requestOk($response)) {
      throw new ApplicationRequestException(
        'Bad status code: ' . $response->getStatusCode()
      );
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self(is_array($content) ? $content : []);
  }

}
