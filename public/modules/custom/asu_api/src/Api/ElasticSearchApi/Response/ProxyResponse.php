<?php

namespace Drupal\asu_api\Api\ElasticSearchApi\Response;

use Drupal\asu_api\Api\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for ProxyRequest.
 */
class ProxyResponse extends Response {

  /**
   * Proxy request content.
   *
   * @var array
   */
  private array $content;

  /**
   * Constructor.
   */
  public function __construct(array $content) {
    $this->content = $content;
  }

  /**
   * Get hits.
   */
  public function getHits() {
    return $this->content;
  }

  /**
   * {@inheritDoc}
   */
  public static function createFromHttpResponse(ResponseInterface $response): ProxyResponse {
    if ($response->getStatusCode() < 200 && $response->getStatusCode() > 299) {
      throw new ApplicationRequestException('Bad status code: ' . $response->getStatusCode());
    }
    $content = json_decode($response->getBody()->getContents(), TRUE);
    return new self($content);
  }

}
