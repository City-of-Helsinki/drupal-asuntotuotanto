<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\ApplicationRequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for pending offer reminders list.
 */
class PendingOfferRemindersResponse extends Response {

  /**
   * Reminder items.
   *
   * @var array
   */
  private array $items;

  /**
   * Constructor.
   */
  public function __construct(array $items) {
    $this->items = $items;
  }

  /**
   * {@inheritdoc}
   */
  public function getContent(): array {
    return $this->items;
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
