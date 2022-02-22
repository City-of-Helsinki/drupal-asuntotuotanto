<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Trigger response class.
 */
class TriggerProjectLotteryResponse extends Response {

  /**
   * Result of the request.
   *
   * @var array
   */
  private array $content;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $content) {
    $this->content = $content;
  }

  /**
   * {@inheritDoc}
   */
  public function getRequestContent() {
    return $this->content;
  }

  /**
   * {@inheritDoc}
   */
  public static function createFromHttpResponse(ResponseInterface $response): self {
    if (!self::requestOk($response)) {
      throw new RequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
