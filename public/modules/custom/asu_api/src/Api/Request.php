<?php

namespace Drupal\asu_api\Api;

use Psr\Http\Message\ResponseInterface;

/**
 * Custom request class.
 */
abstract class Request {

  protected const AUTHENTICATED = FALSE;
  protected const METHOD = 'GET';
  protected const PATH = '';

  /**
   * Gets the HTTP method.
   *
   * @return string
   *   The HTTP method.
   */
  public function getMethod(): string {
    return static::METHOD;
  }

  /**
   * Gets the request path.
   *
   * For example /prices.
   *
   * @return string
   *   The path.
   */
  public function getPath(): string {
    if (!static::PATH) {
      throw new \LogicException('Missing path.');
    }
    return static::PATH;
  }

  /**
   * Gets the request data.
   *
   * @return array
   *   The request.
   */
  public function toArray(): array {
    return [];
  }

  /**
   * Does endpoint require auth token.
   *
   * @return bool
   *   Endpoint requires authentication.
   */
  public function requiresAuthentication(): bool {
    return static::AUTHENTICATED;
  }

  /**
   * Create custom response object for http client response.
   *
   * @param Psr\Http\Message\ResponseInterface $response
   *   Guzzle request to map.
   *
   * @return Drupal\asu_api\Response
   *   Custom response class.
   */
  abstract public static function getResponse(ResponseInterface $response): Response;

}
