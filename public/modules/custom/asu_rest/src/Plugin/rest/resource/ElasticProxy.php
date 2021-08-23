<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\asu_api\Api\ElasticSearchApi\ElasticSearchApi;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get user applications.
 *
 * @RestResource(
 *   id = "asu_elastic_proxy",
 *   label = @Translation("Elastic proxy"),
 *   uri_paths = {
 *     "canonical" = "/elasticproxy",
 *     "create" = "/elasticproxy"
 *   }
 * )
 */
class ElasticProxy extends ResourceBase {
  private ElasticSearchApi $elasticSearchApi;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, ElasticSearchApi $elasticSearchApi) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger, $elasticSearchApi);
    $this->elasticSearchApi = $elasticSearchApi;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->getParameter('serializer.formats'), $container->get('logger.factory')->get('elastic_proxy'), $container->get('asu_api.elasticapi'));
  }

  /**
   * Responds to POST requests.
   *
   * @param array $data
   *   The post data.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function post(array $data) : ModifiedResourceResponse {
    $response = [];
    try {
      $proxyRequest = $this->elasticSearchApi
        ->getApartmentService()
        ->proxyRequest($data);
      $response = $proxyRequest->getHits();
    }
    catch (\Exception $e) {
      \Drupal::logger('asu_elastic_proxy')->critical('Could not fetch apartments for react search component: ' . $e->getMessage());
      return new ModifiedResourceResponse(['message' => 'Proxy query for apartments failed.'], 500);
    }

    $headers = getenv('APP_ENV') == 'test' ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];

    return new ModifiedResourceResponse($response, 200, $headers);
  }

}
