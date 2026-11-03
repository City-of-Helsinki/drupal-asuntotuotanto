<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\ApplicationRequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for marking offer reminder as sent.
 */
class MarkOfferReminderSentResponse extends Response {

  /**
   * Response payload.
   *
   * @var array
   */
  private array $payload;

  /**
   * Constructor.
   */
  public function __construct(array $payload) {
    $this->payload = $payload;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): array {
    return $this->payload;
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
