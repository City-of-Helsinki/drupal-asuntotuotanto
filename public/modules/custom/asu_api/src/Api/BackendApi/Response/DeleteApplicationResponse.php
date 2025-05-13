<?php

namespace Drupal\asu_api\Api\BackendApi\Response;

use Drupal\asu_api\Api\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Response for DeleteApplicationRequest.
 */
class DeleteApplicationResponse extends Response {

  /**
   * Create a new response from HTTP response.
   */
  public static function createFromHttpResponse(ResponseInterface $response): self {
    return new self($response);
  }

}
