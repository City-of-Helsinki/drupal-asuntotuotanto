<?php

namespace Drupal\asu_api\Api\BackendApi\Request;

use Drupal\asu_api\Api\Request;
use Drupal\asu_api\Api\BackendApi\Response\GetIntegrationStatusResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Retrieves the integration status for connections.
 */
class GetIntegrationStatusRequest extends Request {

  /**
   * The HTTP method.
   */
  protected const METHOD = 'GET';

  /**
   * The endpoint path.
   */
  protected const PATH = '/v1/connections/integration_status/';

  /**
   * Whether the request requires an authentication token.
   */
  protected const AUTHENTICATED = TRUE;

  /**
   * Query parameters for the request.
   *
   * @var array
   */
  protected array $queryParams = [];

  /**
   * Constructor.
   *
   * @param array $filters
   * Optional filters (e.g., ['connection_id' => 123, 'active' => true]).
   */
  public function __construct(array $filters = []) {
    $this->sender = NULL;
    $this->queryParams = $filters;
  }

  /**
   * Returns the query parameters for the HTTP client.
   *
   * @return array
   * The array of query parameters.
   */
  public function getQuery(): array {
    return $this->queryParams;
  }

  /**
   * (Optional) specific overrides for the default timeout.
   * * Useful if this specific status check is known to be slow.
   */
  public function getTimeout(): int {
    return 10;
  }

    /**
     * {@inheritdoc}
     */
    public static function getResponse(ResponseInterface $response): GetIntegrationStatusResponse
    {
        return GetIntegrationStatusResponse::createFromHttpResponse($response);
    }
}