<?php

namespace Drupal\asu_rest\Plugin\rest\resource;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\TermInterface;
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
   * Drupal stores monetary values in decimal fields (scale 2). Consumers of
   * this endpoint expect values in cents to match the other services.
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
    // Bump cache namespace when response payload shape changes.
    $cid = 'asu_rest:apartment_list:v7:' . $ownership_type . $url_params;

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
        $room_count_filter = $this->normalizeRoomCountFilter($parameters->get('room_count'));

        [$living_area_min, $living_area_max] = $this->normalizeLivingAreaBoundsForFilter($request_data);
        [$living_area_min, $living_area_max] = $this->sortBounds($living_area_min, $living_area_max);

        [$price_min, $price_max] = $this->normalizePriceBoundsForFilter($request_data);
        [$price_min, $price_max] = $this->sortBounds($price_min, $price_max);

        [$floor_min, $floor_max, $floor_error] = $this->normalizeFloorBoundsForFilter($request_data);
        if ($floor_error !== NULL) {
          return new ModifiedResourceResponse(['message' => $floor_error], 400, $headers);
        }

        $property_options = $this->resolvePropertyOptions($request_data);
        $floor_options = $this->resolveFloorOptions($request_data);

        if ($ownership_type === 'haso') {
          // Legacy FE sends HASO max as 5500 -> 550000000. Convert to
          // comparable payload scale (5 500 000) for HASO cards.
          if ($price_min !== NULL && $price_min >= 100000000) {
            $price_min /= 100;
          }
          if ($price_max !== NULL && $price_max >= 100000000) {
            $price_max /= 100;
          }
        }

        // Query apartments in our projects that pass apartment filters.
        $entity_query = \Drupal::entityQuery('node')
          ->condition('type', 'apartment')
          ->condition('status', 1)
          ->condition('nid', array_values($apartment_ids), 'IN');

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

          if (!$this->matchesProjectPropertyFilters($project_node, $property_options)) {
            continue;
          }

          if (!$this->matchesApartmentPropertyFilters($apartment_node, $property_options)) {
            continue;
          }

          if (!$this->matchesFloorFilters($apartment_node, $floor_options)) {
            continue;
          }

          if (!$this->matchesFloorBoundsFilter($this->getNodeInt($apartment_node, 'field_floor'), $floor_min, $floor_max)) {
            continue;
          }

          if (!$this->matchesRoomCountFilter($this->resolveApartmentRoomCount($apartment_node), $room_count_filter)) {
            continue;
          }

          if (!$this->matchesLivingAreaFilter($this->resolveApartmentLivingArea($apartment_node), $living_area_min, $living_area_max)) {
            continue;
          }

          if (!$this->matchesPriceFilter($apartment_node, $price_min, $price_max, $ownership_type)) {
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
                $project_image_urls[] = $this->normalizeImageUrl((string) $item->entity->getFileUri());
              }
            }
          }

          $project_main_image_url = '';
          if (
            $project_node->hasField('field_main_image_url')
            && !$project_node->get('field_main_image_url')->isEmpty()
          ) {
            $project_main_image_url = $this->normalizeImageUrl((string) $project_node->get('field_main_image_url')->value);
          }
          elseif (
            $project_node->hasField('field_main_image')
            && !$project_node->get('field_main_image')->isEmpty()
            && $project_node->get('field_main_image')->entity
          ) {
            $project_main_image_url = $this->normalizeImageUrl((string) $project_node->get('field_main_image')->entity->getFileUri());
          }

          $project_application_end_time = '';
          if (
            $project_node->hasField('field_project_application_end_time')
            && !$project_node->get('field_project_application_end_time')->isEmpty()
          ) {
            $project_application_end_time = (string) $project_node->get('field_project_application_end_time')->value;
          }
          elseif ($project_node->hasField('field_application_end_time') && !$project_node->get('field_application_end_time')->isEmpty()) {
            $project_application_end_time = (string) $project_node->get('field_application_end_time')->value;
          }

          $project_application_start_time = '';
          if (
            $project_node->hasField('field_project_application_start_time')
            && !$project_node->get('field_project_application_start_time')->isEmpty()
          ) {
            $project_application_start_time = (string) $project_node->get('field_project_application_start_time')->value;
          }
          elseif (
            $project_node->hasField('field_application_start_time')
            && !$project_node->get('field_application_start_time')->isEmpty()
          ) {
            $project_application_start_time = (string) $project_node->get('field_application_start_time')->value;
          }

          $project_can_apply_afterwards = FALSE;
          if (
            $project_node->hasField('field_project_can_apply_afterwards')
            && !$project_node->get('field_project_can_apply_afterwards')->isEmpty()
          ) {
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
          $entity_repository = \Drupal::service('entity.repository');
          $get_term_enum = static function (Node $node, string $field) use ($normalize_enum, $entity_repository): string {
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
            if ($entity instanceof ConfigEntityInterface) {
              return $normalize_enum((string) $entity->id());
            }
            $value = '';
            if (method_exists($entity, 'hasField')
              && $entity->hasField('field_machine_readable_name')
              && !$entity->get('field_machine_readable_name')->isEmpty()) {
              $value = (string) $entity->get('field_machine_readable_name')->value;
            }
            if ($value === '' && $entity instanceof TermInterface) {
              $term = $entity_repository->getTranslationFromContext($entity, 'en');
              $value = trim($term->getName());
            }
            if ($value === '' && isset($item->target_id)) {
              $value = (string) $item->target_id;
            }
            return $normalize_enum($value);
          };
          $get_datetime_values = static function (Node $node, string $field): array {
            if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
              return [];
            }

            $values = [];
            foreach ($node->get($field)->getValue() as $item) {
              if (!empty($item['value']) && is_string($item['value'])) {
                $values[] = $item['value'];
              }
            }

            return array_values(array_unique($values));
          };
          $get_showing_time_field = static function (Node $node): string {
            if ($node->hasField('field_showing_time_range') && !$node->get('field_showing_time_range')->isEmpty()) {
              return 'field_showing_time_range';
            }

            return 'field_showing_time';
          };
          $build_showing_time_ranges = static function (Node $node, string $field): array {
            if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
              return [];
            }

            $ranges = [];
            foreach ($node->get($field)->getValue() as $item) {
              $showing_time = isset($item['value']) && is_string($item['value']) ? $item['value'] : '';
              if ($showing_time === '') {
                continue;
              }

              $start_timestamp = strtotime($showing_time . ' UTC');
              if ($start_timestamp === FALSE) {
                continue;
              }

              $end_value = isset($item['end_value']) && is_string($item['end_value']) ? $item['end_value'] : '';
              $end_timestamp = NULL;
              if ($end_value !== '') {
                $parsed_end_timestamp = strtotime($end_value . ' UTC');
                if ($parsed_end_timestamp !== FALSE && $parsed_end_timestamp > $start_timestamp) {
                  $end_timestamp = $parsed_end_timestamp;
                }
              }

              $start_text = \Drupal::service('date.formatter')->format($start_timestamp, 'custom', 'd.m.Y H:i');

              $display = $start_text;
              $end_output = NULL;
              if ($end_timestamp !== NULL) {
                $same_day = gmdate('Y-m-d', $start_timestamp) === gmdate('Y-m-d', $end_timestamp);
                $end_text = \Drupal::service('date.formatter')->format($end_timestamp, 'custom', $same_day ? 'H:i' : 'd.m.Y H:i');
                $display = $start_text . ' - ' . $end_text;
                $end_output = gmdate('Y-m-d\\TH:i:s', $end_timestamp);
              }

              $ranges[] = [
                'start' => $showing_time,
                'end' => $end_output,
                'display' => $display,
              ];
            }

            return $ranges;
          };

          // Keep state enums in canonical form for FE logic.
          $apartment_state_of_sale = $get_term_enum($apartment_node, 'field_apartment_state_of_sale');
          if ($apartment_state_of_sale === '') {
            $apartment_state_of_sale = $get_term_enum($apartment_node, 'field_state_of_sale');
          }

          if (!$this->isApartmentVisibleInPublicSearch($apartment_state_of_sale)) {
            continue;
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

          $room_count = $this->resolveApartmentRoomCount($apartment_node);
          $showing_time_field = $get_showing_time_field($apartment_node);
          $showing_times = $get_datetime_values($apartment_node, $showing_time_field);
          $showing_time_ranges = $build_showing_time_ranges($apartment_node, $showing_time_field);

          $apartment_number = $get_scalar($apartment_node, 'field_apartment_number');
          $application_url = '';
          if (method_exists($project_node, 'getApplicationUrl')) {
            $computed_url = $project_node->getApplicationUrl(
              $apartment_number,
              $apartment_state_of_sale,
              (int) $apartment_node->id()
            );
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
            'floor' => $get_scalar($apartment_node, 'field_floor') !== ''
              ? intval($get_scalar($apartment_node, 'field_floor'))
              : NULL,
            'floor_max' => $get_scalar($apartment_node, 'field_floor_max') !== ''
              ? intval($get_scalar($apartment_node, 'field_floor_max'))
              : NULL,
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
            'showing_times' => $showing_times,
            'showing_time_ranges' => $showing_time_ranges,
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
   * Whether an apartment should be included in public elasticsearch responses.
   *
   * Sold apartments remain in Drupal and internal search APIs but are hidden
   * from the public apartment search widget.
   */
  protected function isApartmentVisibleInPublicSearch(string $apartmentStateOfSale): bool {
    return strtoupper($apartmentStateOfSale) !== 'SOLD';
  }

  /**
   * Normalize room count filter values from request payload.
   */
  private function normalizeRoomCountFilter(mixed $raw): array {
    if ($raw === NULL || $raw === '') {
      return [];
    }

    $values = is_array($raw) ? $raw : [$raw];
    $normalized = [];

    foreach ($values as $value) {
      if (is_numeric($value)) {
        $normalized[] = (int) $value;
      }
    }

    return array_values(array_unique(array_filter($normalized, static fn (int $v): bool => $v > 0)));
  }

  /**
   * Normalize request properties to lowercase values.
   *
   * @return string[]
   *   Normalized property keys.
   */
  private function normalizePropertyList(mixed $raw): array {
    if ($raw === NULL || $raw === '') {
      return [];
    }

    if (is_string($raw)) {
      $raw = array_map('trim', explode(',', $raw));
    }

    if (!is_array($raw)) {
      return [];
    }

    $normalized = [];
    foreach ($raw as $value) {
      if (!is_scalar($value)) {
        continue;
      }
      $item = strtolower(trim((string) $value));
      if ($item !== '') {
        $normalized[] = $item;
      }
    }

    return array_values(array_unique($normalized));
  }

  /**
   * Normalize mixed query value into nullable boolean.
   */
  private function normalizeBooleanParam(mixed $value): ?bool {
    if ($value === NULL || $value === '') {
      return NULL;
    }

    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return ((int) $value) !== 0;
    }

    if (is_string($value)) {
      $normalized = strtolower(trim($value));
      if (in_array($normalized, ['1', 'true', 'yes', 'on'], TRUE)) {
        return TRUE;
      }
      if (in_array($normalized, ['0', 'false', 'no', 'off'], TRUE)) {
        return FALSE;
      }
    }

    return NULL;
  }

  /**
   * Resolve apartment and project property options from request payload.
   *
   * @return array<string, ?bool>
   *   Normalized property options.
   */
  private function resolvePropertyOptions(array $requestData): array {
    $properties = $this->normalizePropertyList($requestData['properties'] ?? NULL);
    $resolve = function (string $key) use ($requestData, $properties): ?bool {
      $direct = $this->normalizeBooleanParam($requestData[$key] ?? NULL);
      if ($direct !== NULL) {
        return $direct;
      }
      return in_array($key, $properties, TRUE) ? TRUE : NULL;
    };

    return [
      'project_has_elevator' => $resolve('project_has_elevator'),
      'project_has_sauna' => $resolve('project_has_sauna'),
      'has_apartment_sauna' => $resolve('has_apartment_sauna'),
      'has_terrace' => $resolve('has_terrace'),
      'has_balcony' => $resolve('has_balcony'),
      'has_yard' => $resolve('has_yard'),
    ];
  }

  /**
   * Resolve floor-related options from request payload.
   *
   * @return array<string, ?bool>
   *   Normalized floor options.
   */
  private function resolveFloorOptions(array $requestData): array {
    $properties = $this->normalizePropertyList($requestData['properties'] ?? NULL);
    $resolve = function (string $key) use ($requestData, $properties): ?bool {
      $direct = $this->normalizeBooleanParam($requestData[$key] ?? NULL);
      if ($direct !== NULL) {
        return $direct;
      }
      return in_array($key, $properties, TRUE) ? TRUE : NULL;
    };

    return [
      'top_floor' => $resolve('top_floor'),
      'bottom_floor' => $resolve('bottom_floor'),
      'not_top_floor' => $resolve('not_top_floor'),
      'not_bottom_floor' => $resolve('not_bottom_floor'),
    ];
  }

  /**
   * Read a scalar integer field value from a node.
   */
  private function getNodeInt(Node $node, string $fieldName): ?int {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $raw = (string) $node->get($fieldName)->value;
    if ($raw === '' || !is_numeric($raw)) {
      return NULL;
    }

    return (int) $raw;
  }

  /**
   * Read a scalar boolean field value from a node.
   */
  private function getNodeBoolean(Node $node, string $fieldName): ?bool {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return NULL;
    }

    return ((string) $node->get($fieldName)->value) === '1';
  }

  /**
   * Match project-level boolean filters against a project node.
   */
  private function matchesProjectPropertyFilters(Node $project, array $options): bool {
    $checks = [
      'project_has_elevator' => 'field_has_elevator',
      'project_has_sauna' => 'field_has_sauna',
    ];

    foreach ($checks as $optionKey => $fieldName) {
      $expected = $options[$optionKey] ?? NULL;
      if ($expected === NULL) {
        continue;
      }

      $actual = $this->getNodeBoolean($project, $fieldName);
      if ($actual === NULL || $actual !== $expected) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Match apartment-level boolean filters against an apartment node.
   */
  private function matchesApartmentPropertyFilters(Node $apartment, array $options): bool {
    $checks = [
      'has_apartment_sauna' => 'field_has_apartment_sauna',
      'has_terrace' => 'field_has_terrace',
      'has_balcony' => 'field_has_balcony',
      'has_yard' => 'field_has_yard',
    ];

    foreach ($checks as $optionKey => $fieldName) {
      $expected = $options[$optionKey] ?? NULL;
      if ($expected === NULL) {
        continue;
      }

      $actual = $this->getNodeBoolean($apartment, $fieldName);
      if ($actual === NULL || $actual !== $expected) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Match floor-related filters using floor and floor_max.
   */
  private function matchesFloorFilters(Node $apartment, array $options): bool {
    if (!$this->hasAnyNonNullOption($options)) {
      return TRUE;
    }

    $floor = $this->getNodeInt($apartment, 'field_floor');
    $floorMax = $this->getNodeInt($apartment, 'field_floor_max');

    $top = $floor !== NULL && $floorMax !== NULL && $floorMax > 0 && $floor === $floorMax;
    $bottom = $floor !== NULL && $floor === 1;
    $notTop = $floor !== NULL && $floorMax !== NULL && $floorMax > 0 && $floor !== $floorMax;
    $notBottom = $floor !== NULL && $floor !== 1;

    return $this->matchesOptionalBooleanFilters($options, [
      'top_floor' => $top,
      'bottom_floor' => $bottom,
      'not_top_floor' => $notTop,
      'not_bottom_floor' => $notBottom,
    ]);
  }

  /**
   * Check whether at least one filter option is explicitly set.
   */
  private function hasAnyNonNullOption(array $options): bool {
    foreach ($options as $value) {
      if ($value !== NULL) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Match optional boolean filters against computed actual values.
   *
   * @param array<string, ?bool> $options
   *   Requested filter values keyed by option name.
   * @param array<string, bool> $actualValues
   *   Computed boolean values keyed by the same option names.
   */
  private function matchesOptionalBooleanFilters(array $options, array $actualValues): bool {
    foreach ($actualValues as $optionKey => $actual) {
      $expected = $options[$optionKey] ?? NULL;
      if ($expected !== NULL && $expected !== $actual) {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Normalize floor bounds from request payload.
   *
   * Supports normalized frontend contract:
   * - floor_min (optional integer)
   * - floor_max (optional integer)
   *
   * @param array<string, mixed> $requestData
   *   Request payload containing optional floor_min and floor_max values.
   *
   * @return array{0:?int,1:?int,2:?string}
   *   [min, max, error]. Error is non-null for invalid input.
   */
  private function normalizeFloorBoundsForFilter(array $requestData): array {
    [$min, $minError] = $this->normalizeOptionalIntegerParam($requestData['floor_min'] ?? NULL, 'floor_min');
    if ($minError !== NULL) {
      return [NULL, NULL, $minError];
    }

    [$max, $maxError] = $this->normalizeOptionalIntegerParam($requestData['floor_max'] ?? NULL, 'floor_max');
    if ($maxError !== NULL) {
      return [NULL, NULL, $maxError];
    }

    if ($min !== NULL && $max !== NULL && $min > $max) {
      return [NULL, NULL, 'Invalid floor range: floor_min cannot be greater than floor_max.'];
    }

    return [$min, $max, NULL];
  }

  /**
   * Normalize optional integer request value.
   *
   * @param mixed $raw
   *   Raw request value to parse as integer.
   * @param string $key
   *   Request key name used in validation error message.
   *
   * @return array{0:?int,1:?string}
   *   [value, error]. Error is non-null for invalid input.
   */
  private function normalizeOptionalIntegerParam(mixed $raw, string $key): array {
    if ($raw === NULL || $raw === '') {
      return [NULL, NULL];
    }

    if (is_string($raw)) {
      $raw = trim($raw);
      if ($raw === '') {
        return [NULL, NULL];
      }
    }

    if (is_int($raw)) {
      return [$raw, NULL];
    }

    if (is_float($raw) && floor($raw) === $raw) {
      return [(int) $raw, NULL];
    }

    if (is_string($raw) && preg_match('/^-?\d+$/', $raw) === 1) {
      return [(int) $raw, NULL];
    }

    return [NULL, sprintf('Invalid %s. Expected integer value.', $key)];
  }

  /**
   * Check if apartment floor is within optional floor bounds.
   */
  private function matchesFloorBoundsFilter(?int $floor, ?int $min, ?int $max): bool {
    return $this->matchesNumericBounds(
      $floor !== NULL ? (float) $floor : NULL,
      $min !== NULL ? (float) $min : NULL,
      $max !== NULL ? (float) $max : NULL,
    );
  }

  /**
   * Resolve apartment room count with fallback to structure field parsing.
   */
  private function resolveApartmentRoomCount(Node $apartment): ?int {
    if ($apartment->hasField('field_room_count') && !$apartment->get('field_room_count')->isEmpty()) {
      $raw = (string) $apartment->get('field_room_count')->value;
      if ($raw !== '' && is_numeric($raw)) {
        $resolved = (int) $raw;
        if ($resolved > 0) {
          return $resolved;
        }
      }
    }

    $structure = '';
    if ($apartment->hasField('field_apartment_structure') && !$apartment->get('field_apartment_structure')->isEmpty()) {
      $structure = (string) $apartment->get('field_apartment_structure')->value;
    }
    elseif ($apartment->hasField('field_structure') && !$apartment->get('field_structure')->isEmpty()) {
      $structure = (string) $apartment->get('field_structure')->value;
    }

    if ($structure !== '' && preg_match('/\d+/', $structure, $matches) === 1) {
      return (int) $matches[0];
    }

    return NULL;
  }

  /**
   * Check if apartment matches room count filter values.
   */
  private function matchesRoomCountFilter(?int $roomCount, array $filter): bool {
    if (!$filter) {
      return TRUE;
    }
    if ($roomCount === NULL) {
      return FALSE;
    }
    if (in_array($roomCount, $filter, TRUE)) {
      return TRUE;
    }

    return in_array(5, $filter, TRUE) && $roomCount >= 5;
  }

  /**
   * Resolve apartment living area in square meters.
   */
  private function resolveApartmentLivingArea(Node $apartment): ?float {
    if (!$apartment->hasField('field_living_area') || $apartment->get('field_living_area')->isEmpty()) {
      return NULL;
    }

    $raw = (string) $apartment->get('field_living_area')->value;
    if (!is_numeric($raw)) {
      return NULL;
    }

    return (float) $raw;
  }

  /**
   * Normalize request living area bounds to comparable numeric values.
   *
   * Supports legacy forms:
   * - living_area as "min,max" (including ",max" and "min,")
   * - living_area as array [min, max]
   * - explicit living_area_min/living_area_max
   * - aliases area/area_min/area_max.
   *
   * @return array{0:?float,1:?float}
   *   [min, max] in square meters.
   */
  private function normalizeLivingAreaBoundsForFilter(array $requestData): array {
    $rangeParam = $requestData['living_area'] ?? $requestData['area'] ?? NULL;
    [$minRaw, $maxRaw] = $this->extractRangeRawValues(
      $rangeParam,
      $requestData['living_area_min'] ?? $requestData['area_min'] ?? NULL,
      $requestData['living_area_max'] ?? $requestData['area_max'] ?? NULL,
      TRUE,
    );

    $min = $this->normalizePositiveFloat($minRaw);
    $max = $this->normalizePositiveFloat($maxRaw);

    return [$min, $max];
  }

  /**
   * Check if apartment living area is inside optional min/max bounds.
   */
  private function matchesLivingAreaFilter(?float $livingArea, ?float $min, ?float $max): bool {
    return $this->matchesNumericBounds($livingArea, $min, $max);
  }

  /**
   * Normalize request price bounds to comparable numeric values.
   *
   * Legacy FE sends max price in `price` and may send arrays/csv in some
   * environments.
   *
   * @return array{0:?float,1:?float}
   *   [min, max] in comparable numeric scale.
   */
  private function normalizePriceBoundsForFilter(array $requestData): array {
    $priceParam = $requestData['price'] ?? NULL;
    [$minRaw, $maxRaw] = $this->extractRangeRawValues(
      $priceParam,
      $requestData['price_min'] ?? NULL,
      $requestData['price_max'] ?? NULL,
      TRUE,
    );

    $min = $this->normalizePositiveFloat($minRaw);
    $max = $this->normalizePositiveFloat($maxRaw);

    return [$min, $max];
  }

  /**
   * Check if apartment comparable price is inside optional min/max bounds.
   */
  private function matchesPriceFilter(Node $apartment, ?float $priceMin, ?float $priceMax, string $ownershipType): bool {
    $value = $this->resolveComparablePrice($apartment, $ownershipType);
    return $this->matchesNumericBounds($value, $priceMin, $priceMax);
  }

  /**
   * Extract raw min/max values from array/csv/single-value payloads.
   *
   * @return array{0:mixed,1:mixed}
   *   [minRaw, maxRaw]
   */
  private function extractRangeRawValues(mixed $rangeParam, mixed $minRaw, mixed $maxRaw, bool $singleNumericAsMax): array {
    if (is_array($rangeParam)) {
      return $this->extractRangeRawValuesFromArray($rangeParam, $minRaw, $maxRaw, $singleNumericAsMax);
    }

    if (is_string($rangeParam) && str_contains($rangeParam, ',')) {
      return $this->extractRangeRawValuesFromCsv($rangeParam, $minRaw, $maxRaw);
    }

    if ($singleNumericAsMax && $maxRaw === NULL && is_numeric($rangeParam)) {
      $maxRaw = $rangeParam;
    }

    return [$minRaw, $maxRaw];
  }

  /**
   * Extract range raw values from an array payload.
   *
   * @return array{0:mixed,1:mixed}
   *   [minRaw, maxRaw]
   */
  private function extractRangeRawValuesFromArray(array $rangeParam, mixed $minRaw, mixed $maxRaw, bool $singleNumericAsMax): array {
    $values = array_values(array_filter($rangeParam, static fn ($v): bool => $v !== '' && $v !== NULL));
    return $this->extractRangeRawValuesFromIndexedValues($values, $minRaw, $maxRaw, $singleNumericAsMax);
  }

  /**
   * Extract range raw values from a CSV payload.
   *
   * @return array{0:mixed,1:mixed}
   *   [minRaw, maxRaw]
   */
  private function extractRangeRawValuesFromCsv(string $rangeParam, mixed $minRaw, mixed $maxRaw): array {
    $values = array_values(
      array_filter(
        array_map('trim', explode(',', $rangeParam)),
        static fn ($v): bool => $v !== '',
      )
    );

    return $this->extractRangeRawValuesFromIndexedValues($values, $minRaw, $maxRaw, FALSE);
  }

  /**
   * Extract range raw values from indexed values.
   *
   * @return array{0:mixed,1:mixed}
   *   [minRaw, maxRaw]
   */
  private function extractRangeRawValuesFromIndexedValues(array $values, mixed $minRaw, mixed $maxRaw, bool $singleNumericAsMax): array {
    $first = $this->getNumericIndexedValue($values, 0);
    $second = $this->getNumericIndexedValue($values, 1);

    if ($minRaw === NULL && $first !== NULL) {
      $minRaw = $first;
    }
    if ($maxRaw === NULL && $second !== NULL) {
      $maxRaw = $second;
    }

    if ($singleNumericAsMax && $maxRaw === NULL && $minRaw === NULL && $first !== NULL) {
      $maxRaw = $first;
    }

    return [$minRaw, $maxRaw];
  }

  /**
   * Get numeric value by index from values array.
   */
  private function getNumericIndexedValue(array $values, int $index): mixed {
    if (!isset($values[$index]) || !is_numeric($values[$index])) {
      return NULL;
    }

    return $values[$index];
  }

  /**
   * Normalize a positive numeric value, else return NULL.
   */
  private function normalizePositiveFloat(mixed $value): ?float {
    if (!is_numeric($value)) {
      return NULL;
    }

    $normalized = (float) $value;
    return $normalized > 0 ? $normalized : NULL;
  }

  /**
   * Sort min/max bounds when both are present.
   *
   * @return array{0:?float,1:?float}
   *   Bounds ordered as [min, max].
   */
  private function sortBounds(?float $min, ?float $max): array {
    if ($min !== NULL && $max !== NULL && $min > $max) {
      return [$max, $min];
    }

    return [$min, $max];
  }

  /**
   * Match optional numeric bounds against a nullable value.
   */
  private function matchesNumericBounds(?float $value, ?float $min, ?float $max): bool {
    if ($min === NULL && $max === NULL) {
      return TRUE;
    }

    if ($value === NULL) {
      return FALSE;
    }

    if ($min !== NULL && $value < $min) {
      return FALSE;
    }
    if ($max !== NULL && $value > $max) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Resolve comparable apartment price used by listing UI.
   */
  private function resolveComparablePrice(Node $apartment, string $ownershipType): ?float {
    if ($ownershipType === 'haso') {
      // HASO cards display release_payment first, then right_of_occupancy.
      if ($apartment->hasField('field_release_payment') && !$apartment->get('field_release_payment')->isEmpty()) {
        $releaseRaw = (string) $apartment->get('field_release_payment')->value;
        if (is_numeric($releaseRaw) && (float) $releaseRaw > 0) {
          return (float) $this->toCents($releaseRaw);
        }
      }

      if ($apartment->hasField('field_right_of_occupancy_payment') && !$apartment->get('field_right_of_occupancy_payment')->isEmpty()) {
        $rightRaw = (string) $apartment->get('field_right_of_occupancy_payment')->value;
        if (is_numeric($rightRaw)) {
          return (float) $this->toCents($rightRaw);
        }
      }

      return NULL;
    }

    $fieldName = $ownershipType === 'hitas'
      ? 'field_debt_free_sales_price'
      : 'field_sales_price';

    if (!$apartment->hasField($fieldName) || $apartment->get($fieldName)->isEmpty()) {
      return NULL;
    }

    $raw = (string) $apartment->get($fieldName)->value;
    if (!is_numeric($raw)) {
      return NULL;
    }

    return (float) $this->toCents($raw);
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

  /**
   * Normalize image URI/path to an absolute browser URL.
   *
   * @param string $value
   *   Raw file URI (public://...), absolute URL, or relative path.
   * @param string $imageStyle
   *   Drupal image style machine name. Defaults to '3_2_m' for consistent
   *   aspect ratio. Pass empty string to skip style (returns original).
   */
  private function normalizeImageUrl(string $value, string $imageStyle = '3_2_m'): string {
    if ($value === '') {
      return '';
    }

    $lower = strtolower($value);
    if (str_starts_with($lower, 'http://') || str_starts_with($lower, 'https://')) {
      return $value;
    }

    if (str_starts_with($value, 'public://') || str_starts_with($value, 'private://') || str_starts_with($value, 'temporary://')) {
      try {
        if ($imageStyle !== '') {
          $style = \Drupal::service('entity_type.manager')
            ->getStorage('image_style')
            ->load($imageStyle);
          if ($style) {
            return (string) $style->buildUrl($value);
          }
        }
        return (string) \Drupal::service('file_url_generator')->generateAbsoluteString($value);
      }
      catch (\Throwable) {
        return $value;
      }
    }

    if (str_starts_with($value, '/')) {
      return $this->buildAbsoluteUrl($value);
    }

    return $this->buildAbsoluteUrl('/' . ltrim($value, '/'));
  }

}
