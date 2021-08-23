<?php

namespace Drupal\asu_api\Api\ElasticSearchApi\Request;

use Drupal\asu_api\Api\Request;

/**
 * Proxy request to elastic.
 */
class ProxyRequest extends Request {

  protected const METHOD = 'GET';
  protected const PATH = '/_search';

  private $requestArray;

  /**
   * Constructor.
   */
  public function __construct(array $request) {
    $this->requestArray = $request;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return $this->requestArray;
  }

}
