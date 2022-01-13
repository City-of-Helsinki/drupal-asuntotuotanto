<?php

namespace Drupal\asu_api\Api;

use Drupal\asu_api\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * The request class.
 */
abstract class Response {

  /**
   * Create response class from http client response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   Response.
   *
   * @return Response
   *   Response.
   */
  abstract public static function createFromHttpResponse(ResponseInterface $response): Response;

  /**
   * Is request statuscode 2xx.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   Response.
   *
   * @return bool
   *   Is request 2xx.
   *
   * @throws \Exception
   */
  public static function requestOk(ResponseInterface $response): bool {
    if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
      throw new RequestException('Bad status code: ' . $response->getStatusCode());
    }
    return TRUE;
  }

}
