<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for user creation request.
 */
class GetApartmentStatusResponse extends Response {

  /**
   * Result of the request.
   *
   * @var array
   */
  private array $content;

  /**
   * Constructor.
   *
   * @param array $content
   *   Contents of the response.
   */
  public function __construct(array $content) {
    $this->content = $content;
  }

  /**
   * Get profile id returned by create user request.
   *
   * @return string
   *   Profile id in authentication request.
   */
  public function getContent(): string {
    return $this->content;
  }

  /**
   * Create new user response from http response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   Guzzle response.
   *
   * @return GetApartmentStatusResponse
   *   CreateUserResponse.
   *
   * @throws \Exception
   */
  public static function createFromHttpResponse(ResponseInterface $response): GetApartmentStatusResponse {
    if (!self::requestOk($response)) {
      throw new RequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
