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
  public function post() : ModifiedResourceResponse {
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
      $parsed = [];

      foreach($item->getFields() as $key => $field) {
        if (count($field->getValues() ) > 1) {
          $parsed[$key] = $field->getValues();
        }
        else {
          $parsed[$key] = isset($field->getValues()[0]) ? $field->getValues()[0] : '';
        }
      }

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

    if ($parameters->project_ownership_type && !empty($parameters->project_ownership_type)) {
      $baseConditionGroup->addCondition('project_ownership_type', array_map('strtolower', $parameters->project_ownership_type), 'IN');
    }

    if ($parameters->districts && !empty($parameters->districts)) {
      $baseConditionGroup->addCondition('project_district', array_map('strtolower', $parameters->districts), 'IN');
    }

    if ($parameters->project_building_type && !empty($parameters->project_building_type)) {
      $baseConditionGroup->addCondition('project_building_type', array_map('strtolower', $parameters->project_building_type), 'IN');
    }

    if ($parameters->properties && !empty($parameters->properties)) {
      foreach ($parameters->properties as $property) {
        $baseConditionGroup->addCondition($property, TRUE);
      }
    }

    if ($parameters->project_new_development_status && !empty($parameters->project_new_development_status)) {
      $baseConditionGroup->addCondition('new_development_status', array_map('strtolower', $parameters->project_new_development_status), 'IN');
    }

    if ($parameters->project_state_of_sale && !empty($parameters->project_state_of_sale)) {
      $baseConditionGroup->addCondition('project_state_of_sale', array_map('strtolower', $parameters->project_state_of_sale), 'IN');
    }

    if ($parameters->room_count && !empty($parameters->room_count)) {
      $roomCount = $parameters->room_count;
      $group = NULL;

      $key = array_search('5+', $roomCount);
      if ($key !== FALSE) {
        unset($roomCount[$key]);
        if (empty($roomCount)) {
          $baseConditionGroup->addCondition('room_count', 5, '>=');
        }
        else {
          $group = $query->createConditionGroup('OR');
          $group->addCondition('room_count', 5, '>=');
        }
      }

      if (!empty($roomCount)) {
        if ($group) {
          $group->addCondition('room_count', array_map('strtolower', $roomCount), 'IN');
          $baseConditionGroup->addConditionGroup($group);
        }
        else {
          $baseConditionGroup->addCondition('room_count', array_map('strtolower', $roomCount), 'IN');
        }
      }
    }

    if ($parameters->living_area && !empty($parameters->living_area)) {
      $min = isset($parameters->living_area[0]) ? (int) $parameters->living_area[0] : 0;
      $max = isset($parameters->living_area[1]) ? (int) $parameters->living_area[1] : 5000;
      $baseConditionGroup->addCondition('living_area', [$min, $max], 'BETWEEN');
    }

    if ($parameters->debt_free_sales_price && !empty($parameters->living_area)) {
      $baseConditionGroup->addCondition('debt_free_sales_price', $parameters->debt_free_sales_price, '<');
    }

  }

function flatten(array $array) {
$return = array();
array_walk_recursive($array, function($a) use (&$return) { $return[] = $a; });
return $return;
}

}
