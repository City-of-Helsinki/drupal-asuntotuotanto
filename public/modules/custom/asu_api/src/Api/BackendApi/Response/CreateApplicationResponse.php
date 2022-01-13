<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Drupal\asu_api\Exception\ApiException;
use Drupal\asu_api\Exception\ApplicationRequestException;
use Drupal\asu_api\Exception\IllegalApplicationException;
use Drupal\asu_api\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for application request.
 */
class CreateApplicationResponse extends Response {

  /**
   * Content.
   *
   * @var array
   */
  protected array $content;

  /**
   * Constructor.
   *
   * @param array $content
   *   Contents of the response.
   */
  public function __construct(array $content) {
    // @todo Set content as attributes and create setters.
    $this->content = $content;
  }

  /**
   * Get request content.
   */
  public function getContent(): array {
    return $this->content;
  }

  /**
   * Set status code.
   */
  public function setStatus($code) {
    $this->status = $code;
  }

  /**
   * Get status code.
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Create new application response from http response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   Guzzle response.
   *
   * @return ApplicationResponse
   *   ApplicationResponse.
   *
   * @throws \Exception
   */
  public static function createFromHttpResponse(ResponseInterface $response): self {
    if (!self::requestOk($response)) {
      throw new ApplicationRequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

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
    $code = $response->getStatusCode();
    if ($code === 500) {
      throw new ApiException('Backend api error: ' . $response->getBody()->getContents(), $response->getStatusCode());
    }
    if ($code >= 400 && $code <= 499) {
      throw new IllegalApplicationException($response->getBody()->getContents(), $response->getStatusCode());
    }
    if ($code < 200 || $code > 299) {
      throw new RequestException('Bad status code: ' . $response->getStatusCode());
    }
    return TRUE;
  }

}
