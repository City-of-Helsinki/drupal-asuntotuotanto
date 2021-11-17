<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to get user applications.
 *
 * @RestResource(
 *   id = "asu_elasticsearch",
 *   label = @Translation("Elastic search"),
 *   uri_paths = {
 *     "canonical" = "/elasticsearch",
 *     "create" = "/elasticsearch"
 *   }
 * )
 */
class ElasticSearch extends ResourceBase {

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->getParameter('serializer.formats'), $container->get('logger.factory')->get('elastic_proxy'));
  }

  /**
   * Responds to POST requests.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function post(array $data) : ModifiedResourceResponse {
    $parameters = json_decode(\Drupal::request()->getContent());

    $indexes = Index::loadMultiple();
    $index = isset($indexes['apartment']) ? $indexes['apartment'] : reset($indexes);
    $query = $index->query();

    $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
      ->createInstance('direct');
    $parse_mode->setConjunction('AND');
    $query->setParseMode($parse_mode);

    $query->range(0, 10000);

    $this->addConditions($query, $parameters);

    try {
      $results = $query->execute();
    }
    catch (\Exception $e) {
      $this->logger->critical('Could not fetch apartments for react search component: ' . $e->getMessage());
      return new ModifiedResourceResponse(['message' => 'Proxy query for apartments failed.'], 500);
    }

    $response = [];
    foreach ($results->getResultItems() as $item) {
      $parsed = array_map(function($field) {
        if (count($field->getValues() ) > 1) {
          return $field->getValues();
        }
        return isset($field->getValues()[0]) ? $field->getValues()[0] : '';
      }, $item->getFields());

      $response[] = $parsed;
    }

    $headers = getenv('APP_ENV') == 'testing' ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];

    return new ModifiedResourceResponse($response, 200, $headers);
  }

  /**
   * Add conditions to the query.
   */
  private function addConditions(QueryInterface &$query, \stdClass $parameters) {
    $baseConditionGroup = $query->getConditionGroup();

    if ($language = \Drupal::languageManager()->getCurrentLanguage()->getId()) {
      $baseConditionGroup->addCondition('_language', array_map('strtolower', [$language]), 'IN');
    }

    $fieldsIn = [
      'project_ownership_type',
      'project_district',
      'project_building_type',
      'new_development_status',
      'project_state_of_sale,',
    ];

    foreach ($fieldsIn as $field) {
      if (!empty($parameters[$field])) {
        $baseConditionGroup->addCondition($field, array_map('strtolower', $parameters[$field]), 'IN');
      }
    }

    if (!empty($parameters->properties)) {
      foreach ($parameters->properties as $property) {
        $baseConditionGroup->addCondition($property, TRUE);
      }
    }

    if (!empty($parameters->room_count)) {
      $roomCount = $parameters->room_count;

      $key = array_search('5+', $roomCount);
      if ($key === FALSE) {
        $baseConditionGroup->addCondition('room_count', $roomCount, 'IN');
      }
      else {
        unset($roomCount[$key]);
        if (empty($roomCount)) {
          $baseConditionGroup->addCondition('room_count', 5, '>=');
        } else {
          $group = $query->createConditionGroup('OR');
          $group->addCondition('room_count', 5, '>=');
          $group->addCondition('room_count', array_map('strtolower', $roomCount), 'IN');
          $baseConditionGroup->addConditionGroup($group);
        }
      }
    }

    if (!empty($parameters->living_area)) {
      $min = isset($parameters->living_area[0]) ? (int) $parameters->living_area[0] : 0;
      $max = isset($parameters->living_area[1]) ? (int) $parameters->living_area[1] : 5000;
      $baseConditionGroup->addCondition('living_area', [$min, $max], 'BETWEEN');
    }

    if (!empty($parameters->debt_free_sales_price)) {
      $baseConditionGroup->addCondition('debt_free_sales_price', $parameters->debt_free_sales_price, '<');
    }

  }

}
