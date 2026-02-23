<?php

declare(strict_types=1);

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\asu_rest\Service\SearchMapper;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\asu_rest\Service\SearchService;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base class for Elasticsearch-compatible REST resources.
 */
abstract class AsuSearchResourceBase extends ResourceBase {

  private const CACHE_TAG = 'apartment_entity_list';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected SearchService $searchService,
    protected SearchMapper $searchMapper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * Validate price parameters, matching the old ES endpoint behavior.
   */
  protected function validatePriceParams(array $params): ?ModifiedResourceResponse {
    if (isset($params['price']) && empty($params['project_ownership_type'])) {
      $message = "Field project_ownership_type must be set if the 'price' parameter is set.";
      $this->logger->critical(sprintf('Apartment request failed: %s.', $message));
      return new ModifiedResourceResponse(['message' => $message], 500, $this->getTestingHeaders());
    }
    return NULL;
  }

  /**
   * Extract pagination parameters from the request.
   *
   * @return array{offset:int,limit:int}
   *   Offset and limit.
   */
  protected function getPagination(Request $request): array {
    $offset = max(0, (int) $request->query->get('from', 0));
    $limit = (int) $request->query->get('size', 1000);
    if ($limit <= 0) {
      $limit = 1000;
    }
    return [
      'offset' => $offset,
      'limit' => $limit,
    ];
  }

  /**
   * Build a resource response from mapped sources.
   */
  protected function buildResponse(array $sources, int $total, string $indexName): ResourceResponse {
    $payload = $this->searchMapper->buildSearchResponse($sources, $total, $indexName);
    $response = new ResourceResponse($payload, 200, $this->getTestingHeaders());
    $cache = (new CacheableMetadata())
      ->setCacheContexts(['url.query_args'])
      ->setCacheMaxAge((int) (getenv('ASU_REST_API_CACHE_MAX_AGE') ?: 0));
    $response->addCacheableDependency($cache);
    return $response;
  }

  /**
   * Build a cache key from endpoint prefix and query params.
   */
  protected function buildCacheKey(string $prefix, array $params): string {
    $parts = [];
    ksort($params);
    foreach ($params as $key => $value) {
      if (is_array($value)) {
        $parts[] = $key . ':' . implode(',', array_map('strval', $value));
      }
      else {
        $parts[] = $key . ':' . (string) $value;
      }
    }
    $paramString = $parts ? implode('_', $parts) : '';
    return 'asu_rest:' . $prefix . ':v1:' . $paramString;
  }

  /**
   * Whether to bypass cache (e.g. for uid 1 debug).
   */
  protected function isCacheBypass(): bool {
    $account = User::load(\Drupal::currentUser()->id());
    return $account && (int) $account->id() === 1;
  }

  /**
   * Get cached payload if present.
   *
   * @return array|null
   *   Cached payload or NULL on miss.
   */
  protected function getCachedPayload(string $cid): ?array {
    $cached = \Drupal::cache()->get($cid);
    return $cached ? $cached->data : NULL;
  }

  /**
   * Store payload in cache.
   */
  protected function setCachedPayload(string $cid, array $payload): void {
    \Drupal::cache()->set($cid, $payload, Cache::PERMANENT, [self::CACHE_TAG]);
  }

  /**
   * Add testing headers for local development.
   */
  protected function getTestingHeaders(): array {
    return getenv('APP_ENV') === 'testing' ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];
  }

}
