<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\QueryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('elastic_proxy'),
    );
  }

  /**
   * Responds to POST requests.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   */
  public function post(array $data) : ModifiedResourceResponse | ResourceResponse {
    $parameters = new ParameterBag($data);

    $headers = getenv('APP_ENV') == 'testing' ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];

    if ($parameters->get('price') && !$parameters->get('project_ownership_type')) {
      $message = "Field project_ownership_type must be set
       if the 'price' parameter is set.";
      $this->logger->critical(sprintf('Apartment request failed: %s.', $message));
      return new ModifiedResourceResponse(['message' => $message], 500, $headers);
    }

    $ownership_type = $data['project_ownership_type'];
    $url_params = [];

    foreach ($data as $param => $value) {
      if ($param == 'project_ownership_type') {
        continue;
      }
      elseif (is_array($value)) {
        $url_params[] = "{$param}:" . implode(',', $value);
      }
      else {
        $url_params[] = "$param:{$value}";
      }
    }

    $url_params = ($url_params) ? implode('_', $url_params) : '';
    // Setup a cache ID.
    $cid = 'asu_rest:apartment_list:' . $ownership_type . $url_params;

    $account = User::load(\Drupal::currentUser()->id());
    $debug = FALSE;
    if ($account->id() == 1) {
      $debug = TRUE;
    }

    // If a cached entry exists, return it.
    if (!$debug && $cached = \Drupal::cache()->get($cid)) {
      $responseArray = $cached->data;
    }
    else {
      $index = Index::load('apartment_listing');
      $query = $index->query();

      $parse_mode = \Drupal::service('plugin.manager.search_api.parse_mode')
        ->createInstance('direct');
      $parse_mode->setConjunction('AND');
      $query->setParseMode($parse_mode);

      $query->range(0, 1000);

      $this->addConditions($query, $parameters);
      try {
        $results = $query->execute();
        $resultItems = $results->getResultItems();
      }
      catch (\Exception $e) {
        $this->logger->critical('Could not fetch apartments for react search component: ' . $e->getMessage());
        return new ModifiedResourceResponse(['message' => 'Apartment query failed.'], 500);
      }
      // These values must be returned inside array.
      $arrays = [
        'image_urls',
        'project_image_urls',
        'services',
        'project_construction_materials',
      ];

      $apartments = [];
      $fields = [
        '_language',
        'apartment_address',
        'apartment_number',
        'apartment_state_of_sale',
        'apartment_structure',
        'application_url',
        'debt_free_sales_price',
        'floor',
        'floor_max',
        'housing_company_fee',
        'living_area',
        'nid',
        'project_application_end_time',
        'project_application_start_time',
        'project_can_apply_afterwards',
        'project_building_type',
        'project_coordinate_lat',
        'project_coordinate_lon',
        'project_district',
        'project_estimated_completion',
        'project_housing_company',
        'project_id',
        'project_image_urls',
        'project_main_image_url',
        'project_new_development_status',
        'project_ownership_type',
        'project_possession_transfer_date',
        'project_state_of_sale',
        'project_street_address',
        'project_upcoming_description',
        'project_url',
        'project_uuid',
        'release_payment',
        'right_of_occupancy_payment',
        'title',
        'url',
        'uuid',
        'sales_price',
        'room_count',
        'sales_price',
      ];

      foreach ($resultItems as $item) {
        $parsed = [];

        $itemFields = $item->getFields(FALSE);
        foreach ($fields as $fieldName) {
          if (!isset($itemFields[$fieldName])) {
            continue;
          }
          $parsed[$fieldName] = in_array($fieldName, $arrays) ? $itemFields[$fieldName]->getValues()
            : ($itemFields[$fieldName]->getValues()[0] ?? '');
        }

        $apartments[] = $parsed;
      }

      $responseArray = [];
      foreach ($apartments as $apartment) {
        $responseArray[$apartment['project_id']][] = $apartment;
      }

      if (count($responseArray) > 0) {
        \Drupal::cache()->set($cid, $responseArray, Cache::PERMANENT, ['search_api_list:apartment_listing']);
      }
    }

    return new ResourceResponse($responseArray, 200, $headers);
  }

  /**
   * Add conditions to the query.
   */
  private function addConditions(QueryInterface &$query, ParameterBag $parameters) {
    $baseConditionGroup = $query->getConditionGroup();

    if ($language = \Drupal::languageManager()->getCurrentLanguage()->getId()) {
      $baseConditionGroup->addCondition('_language', [$language], 'IN');
    }

    $simpleConditions = [
      'project_ownership_type',
      'project_district',
      'project_building_type',
      'new_development_status',
      'project_building_type',
      'project_has_elevator',
      'project_has_sauna',
      'has_apartment_sauna',
      'has_terrace',
      'has_balcony',
      'has_yard',
    ];

    foreach ($simpleConditions as $field) {
      $value = NULL;
      if ($parameters->get($field)) {
        $isBool = filter_var($parameters->get($field), FILTER_VALIDATE_BOOL);

        if (is_string($parameters->get($field)) && !$isBool) {
          $value = array_map('strtolower', [$parameters->get($field)]);
        }
        elseif (is_array($parameters->get($field))) {
          $value = array_map('strtolower', $parameters->get($field));
        }
        elseif ($isBool) {
          $baseConditionGroup->addCondition($field, $parameters->get($field), '=');
        }
        if (isset($value)) {
          $baseConditionGroup->addCondition($field, $value, 'IN');
        }
      }
    }

    $baseConditionGroup->addCondition('project_published', 'true', '=');
    $baseConditionGroup->addCondition('apartment_published', 'true', '=');
    $baseConditionGroup->addCondition('project_id', NULL, '<>');
    $baseConditionGroup->addCondition('apartment_state_of_sale', 'SOLD', '<>');

    // If no project state of sale is set, return all except upcoming and sold.
    if (empty($parameters->get('project_state_of_sale'))) {
      $baseConditionGroup->addCondition('project_state_of_sale', ['upcoming'], 'NOT IN');
    }
    else {
      $states = array_map('strtolower', $parameters->get('project_state_of_sale'));
      $upcoming = array_search('upcoming', $states, 'IN');
      // Exclude upcoming apartments unless requested.
      if ($upcoming === FALSE) {
        $group = $query->createConditionGroup('OR');
        $group->addCondition('project_state_of_sale', $states, 'IN');
        $group->addCondition('project_state_of_sale', ['upcoming'], 'NOT IN');
        $baseConditionGroup->addConditionGroup($group);
      }
      else {
        $baseConditionGroup->addCondition('project_state_of_sale', ['upcoming'], 'IN');
      }
    }

    if ($parameters->get('properties')) {
      foreach ($parameters->get('properties') as $property) {
        $baseConditionGroup->addCondition(strtolower($property), TRUE);
      }
    }

    if ($roomCount = $parameters->get('room_count')) {
      $key = array_search('5', $roomCount);
      if ($key === FALSE) {
        $baseConditionGroup->addCondition('room_count', $roomCount, 'IN');
      }
      else {
        unset($roomCount[$key]);
        if (empty($roomCount)) {
          $baseConditionGroup->addCondition('room_count', 5, '>=');
        }
        else {
          $group = $query->createConditionGroup('OR');
          $group->addCondition('room_count', 5, '>=');
          $group->addCondition('room_count', $roomCount, 'IN');
          $baseConditionGroup->addConditionGroup($group);
        }
      }
    }

    if (!empty($parameters->get('living_area'))) {
      $min = isset($parameters->get('living_area')[0]) && $parameters->get('living_area')[0] !== ""
            ? (int) $parameters->get('living_area')[0]
            : NULL;
      $max = isset($parameters->get('living_area')[1]) && $parameters->get('living_area')[1] !== ""
            ? (int) $parameters->get('living_area')[1]
            : NULL;

      if ($min !== NULL && $max !== NULL) {
        $baseConditionGroup->addCondition('living_area', [$min, $max], 'BETWEEN');
      }
      elseif ($min !== NULL) {
        $baseConditionGroup->addCondition('living_area', [$min, 99999], 'BETWEEN');
      }
      elseif ($max !== NULL) {
        $baseConditionGroup->addCondition('living_area', [0, $max], 'BETWEEN');
      }
    }

    // @todo Debt free sales price won't be needed in future.
    if ($value = $parameters->get('debt_free_sales_price')) {
      if (!$parameters->get('price')) {
        $baseConditionGroup->addCondition('debt_free_sales_price', $value, '<');
      }
    }

    if ($value = $parameters->get('price')) {
      $field = strtolower($parameters->get('project_ownership_type')) == 'hitas' ?
        'debt_free_sales_price' : 'right_of_occupancy_payment';
      $baseConditionGroup->addCondition($field, $value, '<');
    }
  }

}
