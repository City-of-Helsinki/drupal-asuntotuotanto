<?php

namespace Drupal\asu_api\Api\DrupalApi\Response;

use Drupal\asu_api\Exception\ApplicationRequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for filter request.
 */
class FilterResponse {

  /**
   * Filter request content.
   *
   * @var array
   */
  private array $content;

  /**
   * Construct.
   */
  public function __construct(array $content) {
    $this->content = $content;
  }

  /**
   * Get the request content.
   */
  public function getContent(): array {
    return $this->content;
  }

  /**
   * Create http response.
   */
  public static function createFromHttpResponse(ResponseInterface $response): FilterResponse {
    if ($response->getStatusCode() < 200 && $response->getStatusCode() > 299) {
      throw new ApplicationRequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
