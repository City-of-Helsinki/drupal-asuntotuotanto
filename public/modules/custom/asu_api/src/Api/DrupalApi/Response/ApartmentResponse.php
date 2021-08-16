<?php

namespace Drupal\asu_api\Api\DrupalApi\Response;

use Drupal\asu_api\Api\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for apartment request.
 */
class ApartmentResponse extends Response {

  private string $content;

  /**
   * Constructor.
   */
  public function __construct(string $content) {
    $this->content = $content;
  }

  /**
   * Get response content.
   */
  public function getContent(): string {
    return $this->content;
  }

  /**
   * {@inheritDoc}
   */
  public static function createFromHttpResponse(ResponseInterface $response): Response {
    parent::requestOk($response);
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
