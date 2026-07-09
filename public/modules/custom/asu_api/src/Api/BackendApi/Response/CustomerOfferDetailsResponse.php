<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\ApplicationRequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for customer offer details content.
 */
class CustomerOfferDetailsResponse extends Response {

  /**
   * Offer details payload.
   *
   * @var array
   */
  private array $details;

  /**
   * Constructor.
   */
  public function __construct(array $details) {
    $this->details = $details;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): array {
    return $this->details;
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
