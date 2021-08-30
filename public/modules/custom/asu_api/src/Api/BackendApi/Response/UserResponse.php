<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for user creation request.
 */
class UserResponse extends Response {

  private array $content;

  /**
   * Constructor.
   *
   * @param array $content
   */
  public function __construct(array $content) {
    $this->content = $content;
  }

  /**
   * Get user information.
   *
   * @return array
   *   User information saved in backend.
   */
  public function getUserInformation(): array {
    return $this->content;
  }

  /**
   * {@inheritDoc}
   */
  public static function createFromHttpResponse(ResponseInterface $response): Response {
    if (!self::requestOk($response)) {
      throw new RequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
