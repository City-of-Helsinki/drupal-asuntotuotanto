<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\ApplicationRequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for customer offer message content.
 */
class CustomerOfferMessageResponse extends Response {

  /**
   * Offer message payload.
   *
   * @var array
   */
  private array $message;

  /**
   * Constructor.
   */
  public function __construct(array $message) {
    $this->message = $message;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): array {
    return $this->message;
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
