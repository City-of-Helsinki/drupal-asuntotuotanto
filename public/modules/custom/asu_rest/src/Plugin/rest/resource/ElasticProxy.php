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
  /**
   * Elasticsearch api.
   *
   * @var Drupal\asu_api\Api\ElasticSearchApi\ElasticSearchApi
   */
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
    return new ModifiedResourceResponse($response, 200);
  }

}
