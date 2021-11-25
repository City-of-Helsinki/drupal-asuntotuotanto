<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

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
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->getParameter('serializer.formats'), $container->get('logger.factory')->get('elastic_proxy'));
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
    return new ModifiedResourceResponse(['message' => 'This endpoint is deprecated.'], 500);
  }

}
