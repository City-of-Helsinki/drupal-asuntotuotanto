<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\QueryInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Provides a resource to get user applications. Used by asuntomyynti-react project.
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
  public function post(array $data): ModifiedResourceResponse | ResourceResponse {
    $request_data = isset($data['query']) && is_array($data['query']) ? $data['query'] : $data;
    $parameters = new ParameterBag($request_data);

    $app_env = getenv('APP_ENV');
    $headers = in_array($app_env, ['testing', 'dev'], TRUE) ? [
      'Access-Control-Allow-Origin' => '*',
      'Access-Control-Allow-Methods' => '*',
      'Access-Control-Allow-Headers' => '*',
    ] : [];

    if ($parameters->get('price') && !$parameters->get('project_ownership_type')) {
      $message = "Field project_ownership_type must be set if the 'price' parameter is set.";
      $this->logger->critical(sprintf('Apartment request failed: %s.', $message));
      return new ModifiedResourceResponse(['message' => $message], 500, $headers);
    }

    $ownership_type = $parameters->get('project_ownership_type') ?? 'all';
    $url_params = [];
    foreach ($request_data as $param => $value) {
      if ($param === 'project_ownership_type') {
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
    $cid = 'asu_rest:apartment_list:' . $ownership_type . $url_params;

    $account = User::load(\Drupal::currentUser()->id());
    $debug = $account && $account->id() == 1;

    if (!$debug && ($cached = \Drupal::cache()->get($cid))) {
      $responseArray = $cached->data;
    }
    else {
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

      // Build entity query for published apartments in current language
      $entity_query = \Drupal::entityQuery('node')
        ->condition('type', 'apartment')
        ->condition('status', 1)
        ->condition('langcode', $language);

      // Filter: apartment state of sale
      if ($parameters->get('apartment_state_of_sale')) {
        $entity_query->condition('field_state_of_sale.entity.name', $parameters->get('apartment_state_of_sale'));
      }

      // Filter: room_count (allow array or int)
      if ($parameters->has('room_count')) {
        $room_count_val = $parameters->get('room_count');
        if (is_array($room_count_val)) {
          $entity_query->condition('field_room_count.value', $room_count_val, 'IN');
        } else {
          $entity_query->condition('field_room_count.value', $room_count_val);
        }
      }

      // Filter: price range
      if ($parameters->has('price_min')) {
        $entity_query->condition('field_sales_price.value', $parameters->get('price_min'), '>=');
      }
      if ($parameters->has('price_max')) {
        $entity_query->condition('field_sales_price.value', $parameters->get('price_max'), '<=');
      }

      // Further allow for more filters here as needed

      // Get max 1000 apartments
      $entity_query->range(0, 1000);

      // Query nids
      try {
        $entity_query->accessCheck(TRUE);
        \Drupal::logger('asu_rest')->debug('entity_query: @query', ['@query' => $entity_query]);
        $nids = $entity_query->execute();
      } catch (\Exception $e) {
        $this->logger->critical('Could not fetch apartments from entity_query: ' . $e->getMessage());
        return new ModifiedResourceResponse(['message' => 'Apartment query failed.'], 500, $headers);
      }

      $apartments = [];
      if (!empty($nids)) {
        /** @var \Drupal\node\Entity\Node[] $nodes */
        $nodes = Node::loadMultiple($nids);

        // Apartments are related from project.field_apartments, not apartment.field_project.
        // Build a project lookup once and map apartment ID => project node.
        $project_query = \Drupal::entityQuery('node')
          ->condition('type', 'project')
          ->condition('status', 1)
          ->condition('field_apartments', array_values($nids), 'IN')
          ->accessCheck(TRUE);

        if ($parameters->get('project_ownership_type')) {
          $ownership_type = $parameters->get('project_ownership_type');
          $project_query->condition('field_ownership_type.entity.name', $ownership_type, is_array($ownership_type) ? 'IN' : '=');
        }

        if ($parameters->get('project_district')) {
          $district = $parameters->get('project_district');
          $project_query->condition('field_district.entity.name', $district, is_array($district) ? 'IN' : '=');
        }

        if ($parameters->get('project_building_type')) {
          $building_type = $parameters->get('project_building_type');
          $project_query->condition('field_building_type.entity.name', $building_type, is_array($building_type) ? 'IN' : '=');
        }

        if ($parameters->get('project_state_of_sale')) {
          $project_query->condition('field_state_of_sale.entity.name', $parameters->get('project_state_of_sale'));
        }

        $project_nids = $project_query->execute();
        $projects = Node::loadMultiple($project_nids);

        $apartment_project_map = [];
        foreach ($projects as $project) {
          if (!$project->hasField('field_apartments') || $project->get('field_apartments')->isEmpty()) {
            continue;
          }
          foreach ($project->get('field_apartments')->getValue() as $reference) {
            if (!empty($reference['target_id'])) {
              $apartment_project_map[(int) $reference['target_id']] = $project;
            }
          }
        }

        foreach ($nodes as $apartment_node) {
          $apartment_id = (int) $apartment_node->id();
          $project_node = $apartment_project_map[$apartment_id] ?? NULL;
          if (!$project_node || !$project_node->isPublished()) {
            continue;
          }

          $project_image_urls = [];
          $project_image_field = NULL;
          if ($project_node->hasField('field_images')) {
            $project_image_field = $project_node->get('field_images');
          }
          if ($project_image_field instanceof EntityReferenceFieldItemListInterface) {
            foreach ($project_image_field as $item) {
              if ($item->entity) {
                $project_image_urls[] = $item->entity->getFileUri();
              }
            }
          }

          $project_main_image_url = '';
          if ($project_node->hasField('field_main_image_url') && !$project_node->get('field_main_image_url')->isEmpty()) {
            $project_main_image_url = (string) $project_node->get('field_main_image_url')->value;
          }
          elseif ($project_node->hasField('field_main_image') && !$project_node->get('field_main_image')->isEmpty() && $project_node->get('field_main_image')->entity) {
            $project_main_image_url = $project_node->get('field_main_image')->entity->getFileUri();
          }

          $project_application_end_time = '';
          if ($project_node->hasField('field_project_application_end_time') && !$project_node->get('field_project_application_end_time')->isEmpty()) {
            $project_application_end_time = (string) $project_node->get('field_project_application_end_time')->value;
          }
          elseif ($project_node->hasField('field_application_end_time') && !$project_node->get('field_application_end_time')->isEmpty()) {
            $project_application_end_time = (string) $project_node->get('field_application_end_time')->value;
          }

          $project_application_start_time = '';
          if ($project_node->hasField('field_project_application_start_time') && !$project_node->get('field_project_application_start_time')->isEmpty()) {
            $project_application_start_time = (string) $project_node->get('field_project_application_start_time')->value;
          }
          elseif ($project_node->hasField('field_application_start_time') && !$project_node->get('field_application_start_time')->isEmpty()) {
            $project_application_start_time = (string) $project_node->get('field_application_start_time')->value;
          }

          $project_can_apply_afterwards = FALSE;
          if ($project_node->hasField('field_project_can_apply_afterwards') && !$project_node->get('field_project_can_apply_afterwards')->isEmpty()) {
            $project_can_apply_afterwards = (bool) $project_node->get('field_project_can_apply_afterwards')->value;
          }
          elseif ($project_node->hasField('field_can_apply_afterwards') && !$project_node->get('field_can_apply_afterwards')->isEmpty()) {
            $project_can_apply_afterwards = (bool) $project_node->get('field_can_apply_afterwards')->value;
          }

          $get_scalar = static function (Node $node, string $field): string {
            return $node->hasField($field) && !$node->get($field)->isEmpty()
              ? (string) $node->get($field)->value
              : '';
          };
          $get_term_label = static function (Node $node, string $field): string {
            if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
              return '';
            }
            $entity = $node->get($field)->entity;
            return $entity ? (string) $entity->label() : '';
          };

          $apartment_state_of_sale = $get_term_label($apartment_node, 'field_apartment_state_of_sale');
          if ($apartment_state_of_sale === '') {
            $apartment_state_of_sale = $get_term_label($apartment_node, 'field_state_of_sale');
          }

          $apartment_structure = $get_scalar($apartment_node, 'field_apartment_structure');
          if ($apartment_structure === '') {
            $apartment_structure = $get_scalar($apartment_node, 'field_structure');
          }

          $project_building_type = $get_term_label($project_node, 'field_building_type');
          if ($project_building_type === '') {
            $project_building_type = $get_scalar($project_node, 'field_building_type');
          }

          $project_district = $get_term_label($project_node, 'field_district');
          if ($project_district === '') {
            $project_district = $get_scalar($project_node, 'field_district');
          }

          $project_ownership_type = strtolower($get_term_label($project_node, 'field_ownership_type'));
          $project_state_of_sale = $get_term_label($project_node, 'field_state_of_sale');

          $room_count = NULL;
          if ($get_scalar($apartment_node, 'field_room_count') !== '') {
            $room_count = intval($get_scalar($apartment_node, 'field_room_count'));
          }

          // Build apartment structure (keep in sync with drupal_elasticsearch_endpoint_example.json)
          $apartment = [
            '_language' => $apartment_node->language()->getId(),
            'apartment_address' => $get_scalar($apartment_node, 'field_apartment_address'),
            'apartment_number' => $get_scalar($apartment_node, 'field_apartment_number'),
            'apartment_state_of_sale' => $apartment_state_of_sale,
            'apartment_structure' => $apartment_structure,
            'application_url' => "/application/{$apartment_node->id()}",
            'debt_free_sales_price' => floatval($get_scalar($apartment_node, 'field_debt_free_sales_price') ?: 0),
            'floor' => $get_scalar($apartment_node, 'field_floor') !== '' ? intval($get_scalar($apartment_node, 'field_floor')) : null,
            'floor_max' => $get_scalar($apartment_node, 'field_floor_max') !== '' ? intval($get_scalar($apartment_node, 'field_floor_max')) : null,
            'housing_company_fee' => floatval($get_scalar($apartment_node, 'field_housing_company_fee') ?: 0),
            'living_area' => floatval($get_scalar($apartment_node, 'field_living_area') ?: 0),
            'nid' => intval($apartment_node->id()),
            'project_application_end_time' => $project_application_end_time,
            'project_application_start_time' => $project_application_start_time,
            'project_can_apply_afterwards' => $project_can_apply_afterwards,
            'project_building_type' => $project_building_type,
            'project_coordinate_lat' => $get_scalar($project_node, 'field_coordinate_lat'),
            'project_coordinate_lon' => $get_scalar($project_node, 'field_coordinate_lon'),
            'project_district' => $project_district,
            'project_estimated_completion' => $get_scalar($project_node, 'field_estimated_completion'),
            'project_housing_company' => $project_node->label(),
            'project_id' => intval($project_node->id()),
            'project_image_urls' => $project_image_urls,
            'project_main_image_url' => $project_main_image_url,
            'project_new_development_status' => $get_scalar($project_node, 'field_new_development_status'),
            'project_ownership_type' => $project_ownership_type,
            'project_possession_transfer_date' => $get_scalar($project_node, 'field_possession_transfer_date'),
            'project_state_of_sale' => $project_state_of_sale,
            'project_street_address' => $get_scalar($project_node, 'field_street_address'),
            'project_upcoming_description' => $get_scalar($project_node, 'field_upcoming_description'),
            'project_url' => "/node/{$project_node->id()}",
            'project_uuid' => $project_node->uuid(),
            'release_payment' => floatval($get_scalar($apartment_node, 'field_release_payment') ?: 0),
            'right_of_occupancy_payment' => floatval($get_scalar($apartment_node, 'field_right_of_occupancy_payment') ?: 0),
            'title' => $apartment_node->label(),
            'url' => $get_scalar($apartment_node, 'field_apartment_url'),
            'uuid' => $apartment_node->uuid(),
            'sales_price' => floatval($get_scalar($apartment_node, 'field_sales_price') ?: 0),
            'room_count' => $room_count,
            // For accurate FE structure, add more fields as needed
          ];
          $project_id = $apartment['project_id'];
          $apartments[$project_id][] = $apartment;
        }
      }

      $responseArray = $apartments;
      if (count($responseArray) > 0) {
        \Drupal::cache()->set($cid, $responseArray, Cache::PERMANENT, ['apartment_entity_list']);
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
      'project_uuid',
      'project_id',
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
          // Keep original case for project_district to match exact names.
          if ($field === 'project_district') {
            $value = [$parameters->get($field)];
          }
          else {
            $value = array_map('strtolower', [$parameters->get($field)]);
          }
        }
        elseif (is_array($parameters->get($field))) {
          // Keep original case for project_district to match exact names.
          if ($field === 'project_district') {
            $value = $parameters->get($field);
          }
          else {
            $value = array_map('strtolower', $parameters->get($field));
          }
        }
        elseif ($isBool) {
          $baseConditionGroup->addCondition($field, $parameters->get($field), '=');
        }
        elseif (is_numeric($parameters->get($field))) {
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
      $baseConditionGroup->addCondition('project_state_of_sale', ['UPCOMING'], 'NOT IN');
    }
    else {
      $states = array_map([$this, 'normalizeEnumValue'], $parameters->get('project_state_of_sale'));
      $upcoming = in_array('UPCOMING', $states, TRUE);
      // Exclude upcoming apartments unless requested.
      if ($upcoming === FALSE) {
        $group = $query->createConditionGroup('OR');
        $group->addCondition('project_state_of_sale', $states, 'IN');
        $group->addCondition('project_state_of_sale', ['UPCOMING'], 'NOT IN');
        $baseConditionGroup->addConditionGroup($group);
      }
      else {
        $baseConditionGroup->addCondition('project_state_of_sale', ['UPCOMING'], 'IN');
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

  /**
   * Normalize enum values to match asu_enum indexing.
   *
   * @param string $value
   *   Raw enum value.
   *
   * @return string
   *   Normalized enum value.
   */
  private function normalizeEnumValue(string $value) : string {
    $value = str_replace(' ', '_', $value);
    $value = str_replace('-', '_', $value);
    return strtoupper($value);
  }

}
