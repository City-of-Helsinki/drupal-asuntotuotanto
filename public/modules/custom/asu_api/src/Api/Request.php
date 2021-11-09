<?php

namespace Drupal\asu_api\Api;

/**
 * The request class.
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

  public function requiresAuthentication() {
    return static::AUTHENTICATED;
  }

  /**
   * Create response object which matches this request.
   *
   * @param Drupal\asu_api\api\ApiRequest $request
   * @return Response
   */
  abstract static function getResponse(Request $request): Response;

}
