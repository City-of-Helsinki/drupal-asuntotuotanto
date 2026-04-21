<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\Entity\Node;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Provides a resource to get user applications. Used by asuntomyynti-react.
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
   * Convert a decimal euro string to integer cents.
   *
   * Drupal stores monetary values in decimal fields (scale 2). Consumers of this
   * endpoint expect values in cents to match the other services.
   */
  protected function toCents(?string $value): int {
    if ($value === NULL || $value === '') {
      return 0;
    }
    return (int) round(((float) $value) * 100);
  }

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

    if (!$parameters->get('project_ownership_type')) {
      $message = "Field project_ownership_type must be set.";
      $this->logger->critical(sprintf('Apartment request failed: %s.', $message));
      return new ModifiedResourceResponse(['message' => $message], 500, $headers);
    }

    $ownership_type = strtolower($parameters->get('project_ownership_type'));
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
    // Bump cache namespace when response enum serialization changes.
    $cid = 'asu_rest:apartment_list:v3:' . $ownership_type . $url_params;

    $account = User::load(\Drupal::currentUser()->id());
    $debug = $account && $account->id() == 1;

    if (!$debug && ($cached = \Drupal::cache()->get($cid))) {
      $responseArray = $cached->data;
    }
    else {
      // Query projects first, then apartments from those projects. This avoids
      // excluding projects when apartment count exceeds arbitrary limits.
      $project_query = \Drupal::entityQuery('node')
        ->condition('type', 'project')
        ->condition('status', 1)
        ->accessCheck(TRUE);

      if ($parameters->get('project_ownership_type')) {
        $ownership_type = $parameters->get('project_ownership_type');
        $op = is_array($ownership_type) ? 'IN' : '=';
        $project_query->condition('field_ownership_type.entity.name', $ownership_type, $op);
      }

      if ($parameters->get('project_district')) {
        $district = $parameters->get('project_district');
        $project_query->condition('field_district.entity.name', $district, is_array($district) ? 'IN' : '=');
      }

      if ($parameters->get('project_building_type')) {
        $building_type = $parameters->get('project_building_type');
        $project_query->condition('field_building_type.entity.name', $building_type, is_array($building_type) ? 'IN' : '=');
      }

      // Project state filter. field_state_of_sale references config_terms_term.
      if ($parameters->get('project_state_of_sale')) {
        $states = $parameters->get('project_state_of_sale');
        $states = is_array($states) ? $states : [$states];
        $states = array_map('strtolower', $states);
        $project_query->condition('field_state_of_sale', $states, 'IN');
      }
      else {
        // No filter: return all except upcoming and sold.
        $project_query->condition('field_state_of_sale', ['upcoming', 'sold'], 'NOT IN');
      }

      $project_nids = $project_query->execute();
      $projects = Node::loadMultiple($project_nids);

      // Collect apartment IDs from matching projects.
      $apartment_project_map = [];
      $apartment_ids = [];
      foreach ($projects as $project) {
        foreach ($project->get('field_apartments')->getValue() as $reference) {
          if (!empty($reference['target_id'])) {
            $aid = (int) $reference['target_id'];
            $apartment_ids[$aid] = $aid;
            $apartment_project_map[$aid] = $project;
          }
        }
      }

      $apartments = [];
      if (empty($apartment_ids)) {
        $nids = [];
        $nodes = [];
      }
      else {
        // Query apartments in our projects that pass apartment filters.
        $entity_query = \Drupal::entityQuery('node')
          ->condition('type', 'apartment')
          ->condition('status', 1)
          ->condition('nid', array_values($apartment_ids), 'IN');

        if ($parameters->has('room_count')) {
          $room_count_val = $parameters->get('room_count');
          if (is_array($room_count_val)) {
            $entity_query->condition('field_room_count.value', $room_count_val, 'IN');
          }
          else {
            $entity_query->condition('field_room_count.value', $room_count_val);
          }
        }

        if ($parameters->has('price_min')) {
          $entity_query->condition('field_sales_price.value', $parameters->get('price_min'), '>=');
        }
        if ($parameters->has('price_max')) {
          $entity_query->condition('field_sales_price.value', $parameters->get('price_max'), '<=');
        }

        try {
          $entity_query->accessCheck(TRUE);
          $nids = $entity_query->execute();
        }
        catch (\Exception $e) {
          $this->logger->critical('Could not fetch apartments from entity_query: ' . $e->getMessage());
          return new ModifiedResourceResponse(['message' => 'Apartment query failed.'], 500, $headers);
        }

        /** @var \Drupal\node\Entity\Node[] $nodes */
        $nodes = Node::loadMultiple($nids);
      }

      if (!empty($nodes)) {
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
          $get_term_label = static function (Node $node, string $field, bool $use_untranslated = FALSE): string {
            if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
              return '';
            }
            $entity = $node->get($field)->entity;
            if (!$entity) {
              return '';
            }
            if ($use_untranslated && method_exists($entity, 'getUntranslated')) {
              $entity = $entity->getUntranslated();
            }
            return (string) $entity->label();
          };
          $normalize_enum = static function (string $value): string {
            if ($value === '') {
              return '';
            }
            $value = str_replace('apartment_for_sale', 'for_sale', $value);
            return strtoupper(str_replace([' ', '-'], '_', $value));
          };
          $get_term_enum = static function (Node $node, string $field) use ($normalize_enum): string {
            if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
              return '';
            }
            $item = $node->get($field)->first();
            if (!$item) {
              return '';
            }
            $entity = $item->entity;
            if (!$entity) {
              return '';
            }
            if (method_exists($entity, 'getUntranslated')) {
              $entity = $entity->getUntranslated();
            }
            $value = '';
            if (method_exists($entity, 'hasField')
              && $entity->hasField('field_machine_readable_name')
              && !$entity->get('field_machine_readable_name')->isEmpty()) {
              $value = (string) $entity->get('field_machine_readable_name')->value;
            }
            if ($value === '' && isset($item->target_id)) {
              $value = (string) $item->target_id;
            }
            return $normalize_enum($value);
          };

          // Keep state enums in canonical form for FE logic.
          $apartment_state_of_sale = $get_term_enum($apartment_node, 'field_apartment_state_of_sale');
          if ($apartment_state_of_sale === '') {
            $apartment_state_of_sale = $get_term_enum($apartment_node, 'field_state_of_sale');
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
          $project_state_of_sale = $get_term_enum($project_node, 'field_state_of_sale');

          $room_count = NULL;
          if ($get_scalar($apartment_node, 'field_room_count') !== '') {
            $room_count = intval($get_scalar($apartment_node, 'field_room_count'));
          }

          $apartment_number = $get_scalar($apartment_node, 'field_apartment_number');
          $application_url = '';
          if (method_exists($project_node, 'getApplicationUrl')) {
            $computed_url = $project_node->getApplicationUrl($apartment_number, $apartment_state_of_sale);
            if (is_string($computed_url) && $computed_url !== '') {
              $application_url = $computed_url;
            }
          }

          $apartment = [
            '_language' => $apartment_node->language()->getId(),
            'apartment_published' => $apartment_node->isPublished(),
            'project_published' => $project_node->isPublished(),
            'apartment_address' => $get_scalar($apartment_node, 'field_apartment_address'),
            'apartment_number' => $apartment_number,
            'apartment_state_of_sale' => $apartment_state_of_sale,
            'apartment_structure' => $apartment_structure,
            'application_url' => $application_url,
            'debt_free_sales_price' => $this->toCents($get_scalar($apartment_node, 'field_debt_free_sales_price')),
            'floor' => $get_scalar($apartment_node, 'field_floor') !== '' ? intval($get_scalar($apartment_node, 'field_floor')) : NULL,
            'floor_max' => $get_scalar($apartment_node, 'field_floor_max') !== '' ? intval($get_scalar($apartment_node, 'field_floor_max')) : NULL,
            'housing_company_fee' => $this->toCents($get_scalar($apartment_node, 'field_housing_company_fee')),
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
            'project_url' => $this->buildAbsoluteUrl($project_node->toUrl()->toString()),
            'project_uuid' => $project_node->uuid(),
            'release_payment' => $this->toCents($get_scalar($apartment_node, 'field_release_payment')),
            'right_of_occupancy_payment' => $this->toCents($get_scalar($apartment_node, 'field_right_of_occupancy_payment')),
            'title' => $apartment_node->label(),
            'url' => $this->buildAbsoluteUrl($apartment_node->toUrl()->toString()),
            'uuid' => $apartment_node->uuid(),
            'sales_price' => $this->toCents($get_scalar($apartment_node, 'field_sales_price')),
            'room_count' => $room_count,
            // For accurate FE structure, add more fields as needed.
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
   * Builds an absolute URL using configured base or current request host.
   *
   * Uses ASU_ASUNTOTUOTANTO_URL when set to avoid internal hostnames in URLs
   * when requests arrive via proxy or internal routing.
   */
  private function buildAbsoluteUrl(string $path): string {
    $baseUrl = getenv('ASU_ASUNTOTUOTANTO_URL');
    if ($baseUrl) {
      return rtrim($baseUrl, '/') . $path;
    }
    $request = \Drupal::request();
    $host = $request ? $request->getSchemeAndHttpHost() : '';

    return $host . $path;
  }

}
